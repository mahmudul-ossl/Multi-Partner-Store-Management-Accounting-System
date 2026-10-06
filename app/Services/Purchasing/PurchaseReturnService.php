<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Enums\ApprovalRequestType;
use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Exceptions\ApprovalStateException;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\AuditLogService;
use App\Support\Costing;
use App\Support\Money;
use App\Support\Sequence;
use Illuminate\Support\Facades\DB;

final class PurchaseReturnService
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array{purchase_id: int, transaction_date: string, note?: string|null, items: list<array{purchase_item_id: int, quantity: string|int}>}  $attributes
     */
    public function create(User $actor, array $attributes): PurchaseReturn
    {
        return DB::transaction(function () use ($actor, $attributes): PurchaseReturn {
            $purchase = Purchase::query()->with('items')->lockForUpdate()->findOrFail($attributes['purchase_id']);

            if ($purchase->status !== DocumentStatus::Approved) {
                throw new ApprovalStateException('Only an approved purchase can be returned.');
            }

            $lines = [];
            $total = '0.00';

            foreach ($attributes['items'] as $row) {
                $item = $purchase->items->firstWhere('id', (int) $row['purchase_item_id']);

                if (! $item instanceof PurchaseItem) {
                    throw new ApprovalStateException('That line is not on the purchase.');
                }

                $quantity = Costing::quantity($row['quantity']);
                $already = PurchaseReturnItem::query()
                    ->where('purchase_item_id', $item->id)
                    ->whereHas('purchaseReturn', fn ($query) => $query->whereIn('status', [
                        DocumentStatus::Pending->value,
                        DocumentStatus::Approved->value,
                    ]))
                    ->pluck('quantity');
                $returned = '0.000';

                foreach ($already as $qty) {
                    $returned = bcadd($returned, Costing::quantity((string) $qty), 3);
                }

                $remaining = bcsub(Costing::quantity((string) $item->quantity), $returned, 3);

                if (Costing::compareQty($quantity, '0') !== 1 || Costing::compareQty($quantity, $remaining) === 1) {
                    throw new ApprovalStateException('The return quantity is not available on that line.');
                }

                $lineTotal = Costing::lineTotal($quantity, (string) $item->unit_cost);
                $total = Money::of($total)->add($lineTotal)->amount();
                $lines[] = [
                    'purchase_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $quantity,
                    'unit_cost' => Costing::cost((string) $item->unit_cost),
                    'line_total' => $lineTotal,
                ];
            }

            if ($lines === []) {
                throw new ApprovalStateException('A return needs at least one item.');
            }

            $return = PurchaseReturn::query()->create([
                'reference' => Sequence::next(PurchaseReturn::class, 'reference', 'PRN'),
                'purchase_id' => $purchase->id,
                'supplier_id' => $purchase->supplier_id,
                'warehouse_id' => $purchase->warehouse_id,
                'transaction_date' => $attributes['transaction_date'],
                'total' => $total,
                'note' => $attributes['note'] ?? null,
                'status' => DocumentStatus::Pending,
                'created_by' => $actor->id,
            ]);
            $return->items()->createMany($lines);
            $this->approvals->submit($return, ApprovalRequestType::Purchase, $total, $actor, $return->note);
            $this->audit->record(AuditAction::Created, $return, null, ['reference' => $return->reference, 'total' => $total], $actor);

            return $return->load('items', 'approvalRequest');
        });
    }
}
