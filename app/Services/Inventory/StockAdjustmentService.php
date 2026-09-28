<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\ApprovalRequestType;
use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Enums\StockAdjustmentKind;
use App\Exceptions\ApprovalStateException;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Approvals\ApprovalService;
use App\Services\AuditLogService;
use App\Support\Costing;
use App\Support\Sequence;
use Illuminate\Support\Facades\DB;

final class StockAdjustmentService
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly AuditLogService $audit,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * @param  array{kind: string, product_id: int, warehouse_id: int, quantity: string|int, unit_cost?: string|int|null, transaction_date: string, reason: string}  $attributes
     */
    public function create(User $actor, array $attributes): StockAdjustment
    {
        return DB::transaction(function () use ($actor, $attributes): StockAdjustment {
            $kind = StockAdjustmentKind::from($attributes['kind']);
            $product = Product::query()->findOrFail($attributes['product_id']);
            $warehouse = Warehouse::query()->where('is_active', true)->findOrFail($attributes['warehouse_id']);
            $quantity = Costing::quantity($attributes['quantity']);

            if ($kind === StockAdjustmentKind::Adjustment && ($attributes['direction'] ?? 'increase') === 'decrease') {
                $quantity = Costing::quantity('-'.ltrim($quantity, '-'));
            }

            if ($kind !== StockAdjustmentKind::Adjustment && Costing::compareQty($quantity, '0') !== 1) {
                throw new ApprovalStateException('The quantity must be greater than zero.');
            }

            if ($kind === StockAdjustmentKind::Adjustment && Costing::compareQty($quantity, '0') === 0) {
                throw new ApprovalStateException('The quantity must not be zero.');
            }

            $unitCost = isset($attributes['unit_cost']) && $attributes['unit_cost'] !== null && $attributes['unit_cost'] !== ''
                ? Costing::cost($attributes['unit_cost'])
                : null;

            $increase = $kind === StockAdjustmentKind::Opening
                || ($kind === StockAdjustmentKind::Adjustment && Costing::compareQty($quantity, '0') === 1);

            if ($increase && $unitCost === null) {
                throw new ApprovalStateException('Increasing stock needs a unit cost.');
            }

            $absolute = str_starts_with($quantity, '-') ? Costing::quantity(substr($quantity, 1)) : $quantity;
            $costForAmount = $unitCost ?? $this->inventory->currentCost($product);
            $amount = Costing::lineTotal($absolute, $costForAmount);

            $adjustment = StockAdjustment::query()->create([
                'reference' => Sequence::next(StockAdjustment::class, 'reference', 'SA'),
                'kind' => $kind,
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'transaction_date' => $attributes['transaction_date'],
                'reason' => $attributes['reason'],
                'status' => DocumentStatus::Pending,
                'created_by' => $actor->id,
            ]);

            $this->approvals->submit($adjustment, ApprovalRequestType::StockAdjustment, $amount, $actor, $adjustment->reason);
            $this->audit->record(AuditAction::Created, $adjustment, null, [
                'reference' => $adjustment->reference,
                'kind' => $kind->value,
            ], $actor);

            return $adjustment->load('approvalRequest');
        });
    }
}
