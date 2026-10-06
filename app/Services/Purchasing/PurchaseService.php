<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Enums\ApprovalRequestType;
use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Exceptions\ApprovalStateException;
use App\Exceptions\ImmutableDocumentException;
use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Approvals\ApprovalService;
use App\Services\AuditLogService;
use App\Services\Finance\PartnerFinanceGuard;
use App\Services\Inventory\InventoryPoster;
use App\Services\Ledger\JournalEntryService;
use App\Support\Costing;
use App\Support\Money;
use App\Support\Sequence;
use Illuminate\Support\Facades\DB;

final class PurchaseService
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly AuditLogService $audit,
        private readonly PartnerFinanceGuard $guard,
        private readonly InventoryPoster $poster,
        private readonly JournalEntryService $journal,
    ) {}

    /**
     * @param  array{supplier_id: int, warehouse_id?: int|null, transaction_date: string, paid_amount?: string|int, payment_method?: string|null, financial_account_id?: int|null, note?: string|null, items: list<array{product_id: int, quantity: string|int, unit_cost: string|int}>}  $attributes
     */
    public function create(User $actor, array $attributes): Purchase
    {
        return DB::transaction(function () use ($actor, $attributes): Purchase {
            $purchase = $this->writePurchase(null, $actor, $attributes, DocumentStatus::Pending);
            $this->approvals->submit($purchase, ApprovalRequestType::Purchase, (string) $purchase->total, $actor, $purchase->note);
            $this->audit->record(AuditAction::Created, $purchase, null, $this->snapshot($purchase), $actor);

            return $purchase->load('items', 'approvalRequest');
        });
    }

    /**
     * @param  array{supplier_id: int, warehouse_id?: int|null, transaction_date: string, paid_amount?: string|int, payment_method?: string|null, financial_account_id?: int|null, note?: string|null, items: list<array{product_id: int, quantity: string|int, unit_cost: string|int}>}  $attributes
     */
    public function update(Purchase $purchase, User $actor, array $attributes): Purchase
    {
        return DB::transaction(function () use ($purchase, $actor, $attributes): Purchase {
            if (! $actor->hasRole(RoleName::SuperAdmin->value)) {
                throw new ImmutableDocumentException('Only a Super Admin can edit purchases.');
            }

            $purchase = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if (! in_array($purchase->status, [DocumentStatus::Pending, DocumentStatus::Approved], true)) {
                throw new ImmutableDocumentException('This purchase cannot be edited.');
            }

            if ($purchase->returns()->exists()) {
                throw new ImmutableDocumentException('Purchases with returns cannot be edited.');
            }

            if ($purchase->payments()->exists()) {
                throw new ImmutableDocumentException('Purchases with supplier payments cannot be edited.');
            }

            $before = $this->snapshot($purchase);
            $wasApproved = $purchase->status === DocumentStatus::Approved;

            if ($wasApproved) {
                $this->unpost($purchase, $actor);
            } elseif (! $purchase->isEditable()) {
                throw new ImmutableDocumentException('Approved records cannot be edited.');
            }

            $purchase = $this->writePurchase($purchase, $actor, $attributes, $wasApproved ? DocumentStatus::Approved : DocumentStatus::Pending);

            if ($wasApproved) {
                $this->poster->post($purchase, $actor);
            } else {
                $this->approvals->syncAmount($purchase, ApprovalRequestType::Purchase, (string) $purchase->total);
            }

            $this->audit->record(AuditAction::Updated, $purchase, $before, $this->snapshot($purchase), $actor);

            return $purchase->load('items', 'approvalRequest', 'journalEntry');
        });
    }

    /**
     * @param  array{supplier_id: int, warehouse_id?: int|null, transaction_date: string, paid_amount?: string|int, payment_method?: string|null, financial_account_id?: int|null, note?: string|null, items: list<array{product_id: int, quantity: string|int, unit_cost: string|int}>}  $attributes
     */
    private function writePurchase(?Purchase $purchase, User $actor, array $attributes, DocumentStatus $status): Purchase
    {
        $supplier = Supplier::query()->findOrFail($attributes['supplier_id']);
        $warehouseId = null;

        if (! empty($attributes['warehouse_id'])) {
            $warehouseId = Warehouse::query()->where('is_active', true)->findOrFail($attributes['warehouse_id'])->id;
        }

        $items = $this->items($attributes['items']);
        $total = '0.00';

        foreach ($items as $item) {
            $total = Money::of($total)->add($item['line_total'])->amount();
        }

        if (Money::of($total)->compare('0.00') !== 1) {
            throw new ApprovalStateException('The purchase total must be greater than zero.');
        }

        $paid = Money::of($attributes['paid_amount'] ?? '0')->amount();

        if (Money::of($paid)->compare('0.00') === -1 || Money::of($paid)->compare($total) === 1) {
            throw new ApprovalStateException('The paid amount cannot exceed the purchase total.');
        }

        $method = null;
        $accountId = null;

        if (Money::of($paid)->compare('0.00') === 1) {
            if (empty($attributes['payment_method']) || empty($attributes['financial_account_id'])) {
                throw new ApprovalStateException('A payment needs a method and a financial account.');
            }

            $method = PaymentMethod::from($attributes['payment_method']);
            $account = FinancialAccount::query()->findOrFail($attributes['financial_account_id']);
            $this->guard->assertAccount($account, $method);
            $accountId = $account->id;
        }

        $payload = [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouseId,
            'transaction_date' => $attributes['transaction_date'],
            'total' => $total,
            'paid_amount' => $paid,
            'due_amount' => Money::of($total)->sub($paid)->amount(),
            'payment_method' => $method,
            'financial_account_id' => $accountId,
            'note' => $attributes['note'] ?? null,
            'status' => $status,
        ];

        if ($purchase instanceof Purchase) {
            $purchase->fill($payload);
            $purchase->save();
            $purchase->items()->delete();
            $purchase->items()->createMany($items);

            return $purchase->fresh('items');
        }

        $created = Purchase::query()->create([
            ...$payload,
            'reference' => Sequence::next(Purchase::class, 'reference', 'PUR'),
            'created_by' => $actor->id,
        ]);
        $created->items()->createMany($items);

        return $created->load('items');
    }

    private function unpost(Purchase $purchase, User $actor): void
    {
        $purchase->loadMissing('journalEntry');

        if ($purchase->journalEntry !== null && $purchase->journalEntry->status === DocumentStatus::Completed) {
            $this->journal->reverse($purchase->journalEntry, $actor, 'Edit reversal '.$purchase->reference);
        }

        $purchase->journal_entry_id = null;
        $purchase->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Purchase $purchase): array
    {
        $purchase->loadMissing('items');

        return [
            'reference' => $purchase->reference,
            'supplier_id' => $purchase->supplier_id,
            'warehouse_id' => $purchase->warehouse_id,
            'transaction_date' => $purchase->transaction_date?->toDateString(),
            'total' => (string) $purchase->total,
            'paid_amount' => (string) $purchase->paid_amount,
            'due_amount' => (string) $purchase->due_amount,
            'payment_method' => $purchase->payment_method?->value,
            'financial_account_id' => $purchase->financial_account_id,
            'status' => $purchase->status->value,
            'note' => $purchase->note,
            'items' => $purchase->items->map(fn ($item): array => [
                'product_id' => $item->product_id,
                'quantity' => (string) $item->quantity,
                'unit_cost' => (string) $item->unit_cost,
                'line_total' => (string) $item->line_total,
            ])->all(),
        ];
    }

    /**
     * @param  list<array{product_id: int, quantity: string|int, unit_cost: string|int}>  $rows
     * @return list<array{product_id: int, quantity: string, unit_cost: string, line_total: string}>
     */
    private function items(array $rows): array
    {
        if ($rows === []) {
            throw new ApprovalStateException('A purchase needs at least one item.');
        }

        $items = [];

        foreach ($rows as $row) {
            $product = Product::query()->find($row['product_id']);

            if (! $product instanceof Product) {
                throw new ApprovalStateException('A purchase item product is missing.');
            }

            $quantity = Costing::quantity($row['quantity']);

            if (Costing::compareQty($quantity, '0') !== 1) {
                throw new ApprovalStateException('Each purchase quantity must be greater than zero.');
            }

            $cost = Costing::cost($row['unit_cost']);
            $items[] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_cost' => $cost,
                'line_total' => Costing::lineTotal($quantity, $cost),
            ];
        }

        return $items;
    }
}
