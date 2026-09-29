<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\DocumentStatus;
use App\Enums\StockMovementType;
use App\Exceptions\ApprovalStateException;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\SalesCancellation;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Ledger\JournalEntryService;
use App\Support\ChartAccountCode;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Posts sales documents after final approval.
 */
final class SalesPoster
{
    public function __construct(
        private readonly SaleCompletion $completion,
        private readonly InventoryService $inventory,
        private readonly JournalEntryService $journal,
        private readonly SaleRules $rules,
    ) {}

    public function post(Model $document, User $actor): void
    {
        match (true) {
            $document instanceof Sale => $this->completion->complete($document, $actor),
            $document instanceof Refund => $this->refund($document, $actor),
            $document instanceof SalesCancellation => $this->cancel($document, $actor),
            default => throw new ApprovalStateException('This approval type does not post a sale.'),
        };
    }

    private function refund(Refund $document, User $actor): void
    {
        DB::transaction(function () use ($document, $actor): void {
            $document->load('financialAccount.chartOfAccount');
            $sale = Sale::query()->whereKey($document->sale_id)->lockForUpdate()->firstOrFail();

            if ($sale->status !== DocumentStatus::Completed) {
                throw new ApprovalStateException('Only a completed sale can be refunded.');
            }

            $credit = Money::of((string) $sale->due_amount)->compare('0.00') === -1
                ? Money::of('0')->sub((string) $sale->due_amount)->amount()
                : '0.00';

            if (Money::of((string) $document->amount)->compare($credit) === 1) {
                throw new ApprovalStateException('The refund is larger than the customer credit.');
            }

            $sale->due_amount = Money::of((string) $sale->due_amount)->add((string) $document->amount)->amount();
            $sale->paid_amount = Money::of((string) $sale->paid_amount)->sub((string) $document->amount)->amount();
            $sale->save();

            $description = 'Refund '.$document->reference;
            $account = $document->financialAccount;
            $entry = $this->journal->post($document, $actor, $document->transaction_date->toDateString(), $description, [
                [
                    'account_code' => ChartAccountCode::AccountsReceivable,
                    'customer_id' => $document->customer_id,
                    'debit' => (string) $document->amount,
                    'credit' => '0.00',
                    'description' => $description,
                ],
                [
                    'account_code' => $account->chartOfAccount->code,
                    'financial_account_id' => $account->id,
                    'customer_id' => $document->customer_id,
                    'debit' => '0.00',
                    'credit' => (string) $document->amount,
                    'description' => $description,
                ],
            ]);
            $document->journal_entry_id = $entry->id;
            $document->save();
        });
    }

    private function cancel(SalesCancellation $document, User $actor): void
    {
        DB::transaction(function () use ($document, $actor): void {
            $sale = Sale::query()->with('items.product', 'warehouse', 'journalEntry')->whereKey($document->sale_id)->lockForUpdate()->firstOrFail();
            $this->rules->assertCancellable($sale, $document->id);

            foreach ($sale->items as $item) {
                $this->inventory->apply(
                    $item->product,
                    $sale->warehouse,
                    StockMovementType::SaleReturn,
                    (string) $item->quantity,
                    (string) $item->unit_cost,
                    $document,
                    $actor,
                    $document->transaction_date->toDateString(),
                    $document->reason,
                );
            }

            $reversal = $this->journal->reverse($sale->journalEntry, $actor, 'Cancel sale '.$sale->reference);
            $document->journal_entry_id = $reversal->id;
            $document->save();

            $sale->status = DocumentStatus::Cancelled;
            $sale->paid_amount = '0.00';
            $sale->due_amount = '0.00';
            $sale->save();
        });
    }
}
