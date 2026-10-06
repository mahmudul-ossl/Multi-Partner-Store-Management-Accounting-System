<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\AuditAction;
use App\Enums\StockAdjustmentKind;
use App\Enums\StockMovementType;
use App\Exceptions\ApprovalStateException;
use App\Models\JournalEntry;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\StockAdjustment;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Ledger\JournalEntryService;
use App\Services\OperationalNotifier;
use App\Support\ChartAccountCode;
use App\Support\Costing;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Posts inventory documents after final approval.
 */
final class InventoryPoster
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly JournalEntryService $journal,
        private readonly AuditLogService $audit,
        private readonly OperationalNotifier $alerts,
    ) {}

    public function post(Model $document, User $actor): void
    {
        $entry = match (true) {
            $document instanceof Purchase => $this->purchase($document, $actor),
            $document instanceof PurchaseReturn => $this->purchaseReturn($document, $actor),
            $document instanceof SupplierPayment => $this->payment($document, $actor),
            $document instanceof StockAdjustment => $this->adjustment($document, $actor),
            default => throw new ApprovalStateException('This approval type does not post inventory.'),
        };

        $document->journal_entry_id = $entry->id;
        $document->save();

        if ($document instanceof Purchase && Money::of((string) $document->due_amount)->compare('0.00') === 1) {
            $this->alerts->supplierDue($document);
        }

        if ($document instanceof SupplierPayment) {
            $this->audit->record(AuditAction::Payment, $document, null, [
                'reference' => $document->reference,
                'amount' => (string) $document->amount,
            ], $actor);
        }

        if ($document instanceof StockAdjustment) {
            $this->audit->record(AuditAction::StockAdjusted, $document, null, [
                'reference' => $document->reference,
                'kind' => $document->kind->value,
            ], $actor);
        }
    }

    private function purchase(Purchase $document, User $actor): JournalEntry
    {
        $document->load('items.product', 'financialAccount.chartOfAccount');

        $description = 'Purchase '.$document->reference;
        $lines = [[
            'account_code' => ChartAccountCode::Cogs,
            'supplier_id' => $document->supplier_id,
            'debit' => (string) $document->total,
            'credit' => '0.00',
            'description' => $description,
        ]];

        if (Money::of((string) $document->paid_amount)->compare('0.00') === 1) {
            $account = $document->financialAccount;
            if ($account === null) {
                throw new ApprovalStateException('A paid purchase needs a financial account.');
            }

            $lines[] = [
                'account_code' => $account->chartOfAccount->code,
                'financial_account_id' => $account->id,
                'supplier_id' => $document->supplier_id,
                'debit' => '0.00',
                'credit' => (string) $document->paid_amount,
                'description' => $description,
            ];
        }

        if (Money::of((string) $document->due_amount)->compare('0.00') === 1) {
            $lines[] = [
                'account_code' => ChartAccountCode::AccountsPayable,
                'supplier_id' => $document->supplier_id,
                'debit' => '0.00',
                'credit' => (string) $document->due_amount,
                'description' => $description,
            ];
        }

        return $this->journal->post($document, $actor, $document->transaction_date->toDateString(), $description, $lines);
    }

    private function purchaseReturn(PurchaseReturn $document, User $actor): JournalEntry
    {
        $document->load('items.product', 'purchase');

        $purchase = Purchase::query()->whereKey($document->purchase_id)->lockForUpdate()->firstOrFail();
        $purchase->due_amount = Money::of((string) $purchase->due_amount)->sub((string) $document->total)->amount();
        $purchase->save();
        $this->alerts->supplierDue($purchase);

        $description = 'Purchase return '.$document->reference;

        return $this->journal->post($document, $actor, $document->transaction_date->toDateString(), $description, [
            [
                'account_code' => ChartAccountCode::AccountsPayable,
                'supplier_id' => $document->supplier_id,
                'debit' => (string) $document->total,
                'credit' => '0.00',
                'description' => $description,
            ],
            [
                'account_code' => ChartAccountCode::Cogs,
                'supplier_id' => $document->supplier_id,
                'debit' => '0.00',
                'credit' => (string) $document->total,
                'description' => $description,
            ],
        ]);
    }

    private function payment(SupplierPayment $document, User $actor): JournalEntry
    {
        $document->load('financialAccount.chartOfAccount', 'purchase');

        if ($document->purchase_id !== null) {
            $purchase = Purchase::query()->whereKey($document->purchase_id)->lockForUpdate()->firstOrFail();

            if (Money::of((string) $document->amount)->compare((string) $purchase->due_amount) === 1) {
                throw new ApprovalStateException('The payment is larger than the purchase due.');
            }

            $purchase->due_amount = Money::of((string) $purchase->due_amount)->sub((string) $document->amount)->amount();
            $purchase->paid_amount = Money::of((string) $purchase->paid_amount)->add((string) $document->amount)->amount();
            $purchase->save();
            $this->alerts->supplierDue($purchase);
        }

        $description = 'Supplier payment '.$document->reference;
        $account = $document->financialAccount;

        return $this->journal->post($document, $actor, $document->payment_date->toDateString(), $description, [
            [
                'account_code' => ChartAccountCode::AccountsPayable,
                'supplier_id' => $document->supplier_id,
                'debit' => (string) $document->amount,
                'credit' => '0.00',
                'description' => $description,
            ],
            [
                'account_code' => $account->chartOfAccount->code,
                'financial_account_id' => $account->id,
                'supplier_id' => $document->supplier_id,
                'debit' => '0.00',
                'credit' => (string) $document->amount,
                'description' => $description,
            ],
        ]);
    }

    private function adjustment(StockAdjustment $document, User $actor): JournalEntry
    {
        $document->load('product', 'warehouse');
        $quantity = Costing::quantity((string) $document->quantity);
        $negative = str_starts_with($quantity, '-');
        $absolute = $negative ? Costing::quantity(substr($quantity, 1)) : $quantity;
        $increase = match ($document->kind) {
            StockAdjustmentKind::Opening => true,
            StockAdjustmentKind::Damage => false,
            StockAdjustmentKind::Adjustment => ! $negative,
        };

        $type = match ($document->kind) {
            StockAdjustmentKind::Opening => StockMovementType::Opening,
            StockAdjustmentKind::Damage => StockMovementType::Damage,
            StockAdjustmentKind::Adjustment => StockMovementType::Adjustment,
        };

        $movement = $this->inventory->apply(
            $document->product,
            $document->warehouse,
            $type,
            $absolute,
            $document->unit_cost !== null ? (string) $document->unit_cost : null,
            $document,
            $actor,
            $document->transaction_date->toDateString(),
            $document->reason,
            $increase ? 1 : -1,
        );

        $amount = Costing::lineTotal($absolute, (string) $movement->unit_cost);

        if (Money::of($amount)->isZero()) {
            throw new ApprovalStateException('Stock with no cost cannot be posted to the ledger.');
        }

        $description = $document->kind->label().' '.$document->reference;
        $inventory = [
            'account_code' => ChartAccountCode::Inventory,
            'debit' => $increase ? $amount : '0.00',
            'credit' => $increase ? '0.00' : $amount,
            'description' => $description,
        ];
        $offsetCode = match ($document->kind) {
            StockAdjustmentKind::Opening => ChartAccountCode::OpeningBalanceEquity,
            StockAdjustmentKind::Damage => ChartAccountCode::OtherExpenses,
            StockAdjustmentKind::Adjustment => $increase ? ChartAccountCode::OtherRevenue : ChartAccountCode::OtherExpenses,
        };
        $offset = [
            'account_code' => $offsetCode,
            'debit' => $increase ? '0.00' : $amount,
            'credit' => $increase ? $amount : '0.00',
            'description' => $description,
        ];

        return $this->journal->post(
            $document,
            $actor,
            $document->transaction_date->toDateString(),
            $description,
            $increase ? [$inventory, $offset] : [$offset, $inventory],
        );
    }
}
