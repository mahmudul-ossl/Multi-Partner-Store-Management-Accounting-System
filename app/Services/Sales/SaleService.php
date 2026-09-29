<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\ApprovalRequestType;
use App\Enums\AuditAction;
use App\Enums\CustomerStatus;
use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\ApprovalStateException;
use App\Models\Customer;
use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Approvals\ApprovalService;
use App\Services\AuditLogService;
use App\Services\Finance\PartnerFinanceGuard;
use App\Support\Costing;
use App\Support\Money;
use App\Support\SaleMath;
use App\Support\Sequence;
use Illuminate\Support\Facades\DB;

final class SaleService
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly AuditLogService $audit,
        private readonly PartnerFinanceGuard $guard,
        private readonly SalesSettings $settings,
        private readonly SaleCompletion $completion,
    ) {}

    /**
     * @param  array{customer_id: int, warehouse_id: int, transaction_date: string, discount?: string|int, delivery?: string|int, paid_amount?: string|int, payment_method?: string|null, financial_account_id?: int|null, note?: string|null, items: list<array{product_id: int, quantity: string|int, unit_price: string|int}>}  $attributes
     */
    public function create(User $actor, array $attributes): Sale
    {
        return DB::transaction(function () use ($actor, $attributes): Sale {
            $customer = Customer::query()->where('status', CustomerStatus::Active)->findOrFail($attributes['customer_id']);
            $warehouse = Warehouse::query()->where('is_active', true)->findOrFail($attributes['warehouse_id']);
            $items = $this->items($attributes['items']);
            $subtotal = '0.00';

            foreach ($items as $item) {
                $subtotal = Money::of($subtotal)->add($item['line_subtotal'])->amount();
            }

            $discount = Money::of($attributes['discount'] ?? '0')->amount();
            $delivery = Money::of($attributes['delivery'] ?? '0')->amount();

            if (Money::of($discount)->compare('0.00') === -1 || Money::of($discount)->compare($subtotal) === 1) {
                throw new ApprovalStateException('The discount cannot exceed the subtotal.');
            }

            if (Money::of($delivery)->compare('0.00') === -1) {
                throw new ApprovalStateException('Delivery cannot be negative.');
            }

            $total = SaleMath::total($subtotal, $discount, $delivery);

            if (Money::of($total)->compare('0.00') !== 1) {
                throw new ApprovalStateException('The sale total must be greater than zero.');
            }

            $paid = Money::of($attributes['paid_amount'] ?? '0')->amount();

            if (Money::of($paid)->compare('0.00') === -1 || Money::of($paid)->compare($total) === 1) {
                throw new ApprovalStateException('The paid amount cannot exceed the sale total.');
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

            $sale = Sale::query()->create([
                'reference' => Sequence::next(Sale::class, 'reference', 'SAL'),
                'customer_id' => $customer->id,
                'warehouse_id' => $warehouse->id,
                'transaction_date' => $attributes['transaction_date'],
                'subtotal' => $subtotal,
                'discount' => $discount,
                'delivery' => $delivery,
                'total' => $total,
                'paid_amount' => $paid,
                'due_amount' => Money::of($total)->sub($paid)->amount(),
                'payment_method' => $method,
                'financial_account_id' => $accountId,
                'note' => $attributes['note'] ?? null,
                'status' => DocumentStatus::Pending,
                'created_by' => $actor->id,
            ]);
            $sale->items()->createMany($items);

            if ($this->settings->needsApproval($discount)) {
                $this->approvals->submit($sale, ApprovalRequestType::LargeDiscount, $discount, $actor, $sale->note);
                $this->audit->record(AuditAction::Created, $sale, null, [
                    'reference' => $sale->reference,
                    'discount' => $discount,
                    'status' => DocumentStatus::Pending->value,
                ], $actor);

                return $sale->load('items', 'approvalRequest');
            }

            $sale = $this->completion->complete($sale, $actor);
            $this->audit->record(AuditAction::Created, $sale, null, [
                'reference' => $sale->reference,
                'total' => (string) $sale->total,
                'status' => DocumentStatus::Completed->value,
            ], $actor);

            return $sale->load('items');
        });
    }

    /**
     * @param  list<array{product_id: int, quantity: string|int, unit_price: string|int}>  $rows
     * @return list<array<string, mixed>>
     */
    private function items(array $rows): array
    {
        $items = [];

        foreach ($rows as $row) {
            $product = Product::query()->findOrFail($row['product_id']);
            $quantity = Costing::quantity($row['quantity']);
            $price = Money::of($row['unit_price'])->amount();
            $subtotal = Costing::lineTotal($quantity, Costing::cost($price));

            if (Costing::compareQty($quantity, '0') !== 1 || Money::of($subtotal)->compare('0.00') !== 1) {
                throw new ApprovalStateException('Each line needs a quantity and a price.');
            }

            $items[] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $price,
                'line_subtotal' => $subtotal,
                'net_amount' => '0.00',
            ];
        }

        return $items;
    }
}
