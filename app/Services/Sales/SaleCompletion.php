<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\DocumentStatus;
use App\Enums\StockMovementType;
use App\Exceptions\ApprovalStateException;
use App\Models\Sale;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Ledger\JournalEntryService;
use App\Support\ChartAccountCode;
use App\Support\Costing;
use App\Support\Money;
use App\Support\SaleMath;
use Illuminate\Support\Facades\DB;

/**
 * Posts a pending sale: stock leaves at the current weighted-average cost,
 * then the journal debits cash and/or receivable and COGS, and credits
 * sales revenue and inventory. Phase 6 expenses should not reuse 4000 for
 * delivery costs; customer delivery is part of this revenue total.
 */
final class SaleCompletion
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly JournalEntryService $journal,
    ) {}

    public function complete(Sale $sale, User $actor): Sale
    {
        return DB::transaction(function () use ($sale, $actor): Sale {
            $sale = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if (! in_array($sale->status, [DocumentStatus::Pending, DocumentStatus::Approved], true)) {
                throw new ApprovalStateException('Only a pending sale can be completed.');
            }

            if ($sale->journal_entry_id !== null) {
                throw new ApprovalStateException('This sale is already posted.');
            }

            $sale->load('items.product', 'warehouse', 'financialAccount.chartOfAccount');
            $nets = SaleMath::allocate(
                $sale->items->map(fn ($item): string => (string) $item->line_subtotal)->all(),
                (string) $sale->total,
            );

            $cogsTotal = '0.00';

            foreach ($sale->items as $index => $item) {
                $movement = $this->inventory->apply(
                    $item->product,
                    $sale->warehouse,
                    StockMovementType::Sale,
                    (string) $item->quantity,
                    null,
                    $sale,
                    $actor,
                    $sale->transaction_date->toDateString(),
                    $sale->reference,
                );
                $cogs = Costing::lineTotal((string) $item->quantity, (string) $movement->unit_cost);
                $item->net_amount = $nets[$index];
                $item->unit_cost = Costing::cost((string) $movement->unit_cost);
                $item->cogs_amount = $cogs;
                $item->save();
                $cogsTotal = Money::of($cogsTotal)->add($cogs)->amount();
            }

            $description = 'Sale '.$sale->reference;
            $lines = [];

            if (Money::of((string) $sale->paid_amount)->compare('0.00') === 1) {
                $account = $sale->financialAccount;
                if ($account === null) {
                    throw new ApprovalStateException('A paid sale needs a financial account.');
                }

                $lines[] = [
                    'account_code' => $account->chartOfAccount->code,
                    'financial_account_id' => $account->id,
                    'customer_id' => $sale->customer_id,
                    'debit' => (string) $sale->paid_amount,
                    'credit' => '0.00',
                    'description' => $description,
                ];
            }

            if (Money::of((string) $sale->due_amount)->compare('0.00') === 1) {
                $lines[] = [
                    'account_code' => ChartAccountCode::AccountsReceivable,
                    'customer_id' => $sale->customer_id,
                    'debit' => (string) $sale->due_amount,
                    'credit' => '0.00',
                    'description' => $description,
                ];
            }

            $lines[] = [
                'account_code' => ChartAccountCode::ProductSales,
                'customer_id' => $sale->customer_id,
                'debit' => '0.00',
                'credit' => (string) $sale->total,
                'description' => $description,
            ];

            if (Money::of($cogsTotal)->compare('0.00') === 1) {
                $lines[] = [
                    'account_code' => ChartAccountCode::Cogs,
                    'customer_id' => $sale->customer_id,
                    'debit' => $cogsTotal,
                    'credit' => '0.00',
                    'description' => $description,
                ];
                $lines[] = [
                    'account_code' => ChartAccountCode::Inventory,
                    'customer_id' => $sale->customer_id,
                    'debit' => '0.00',
                    'credit' => $cogsTotal,
                    'description' => $description,
                ];
            }

            $entry = $this->journal->post($sale, $actor, $sale->transaction_date->toDateString(), $description, $lines);
            $sale->journal_entry_id = $entry->id;
            $sale->status = DocumentStatus::Completed;
            $sale->save();

            return $sale->fresh('items');
        });
    }
}
