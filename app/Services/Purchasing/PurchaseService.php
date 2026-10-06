<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Enums\ApprovalRequestType;
use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\ApprovalStateException;
use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Approvals\ApprovalService;
use App\Services\AuditLogService;
use App\Services\Finance\PartnerFinanceGuard;
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
    ) {}

    /**
     * @param  array{supplier_id: int, warehouse_id: int, transaction_date: string, paid_amount?: string|int, payment_method?: string|null, financial_account_id?: int|null, note?: string|null, items: list<array{product_id: int, quantity: string|int, unit_cost: string|int}>}  $attributes
     */
    public function create(User $actor, array $attributes): Purchase
    {
        return DB::transaction(function () use ($actor, $attributes): Purchase {
            $supplier = Supplier::query()->findOrFail($attributes['supplier_id']);
            $warehouse = Warehouse::query()->where('is_active', true)->findOrFail($attributes['warehouse_id']);
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

            $purchase = Purchase::query()->create([
                'reference' => Sequence::next(Purchase::class, 'reference', 'PUR'),
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'transaction_date' => $attributes['transaction_date'],
                'total' => $total,
                'paid_amount' => $paid,
                'due_amount' => Money::of($total)->sub($paid)->amount(),
                'payment_method' => $method,
                'financial_account_id' => $accountId,
                'note' => $attributes['note'] ?? null,
                'status' => DocumentStatus::Pending,
                'created_by' => $actor->id,
            ]);

            $purchase->items()->createMany($items);
            $this->approvals->submit($purchase, ApprovalRequestType::Purchase, $total, $actor, $purchase->note);
            $this->audit->record(AuditAction::Created, $purchase, null, [
                'reference' => $purchase->reference,
                'total' => $total,
            ], $actor);

            return $purchase->load('items', 'approvalRequest');
        });
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
