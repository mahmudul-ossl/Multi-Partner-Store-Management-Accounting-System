<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Exceptions\ApprovalStateException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OperationalNotifier;
use App\Support\Costing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of stock_movements. On-hand and weighted-average cost
 * are derived here. Phase 5 should call apply() for sales and sale returns
 * and use the returned unit_cost as COGS.
 */
final class InventoryService
{
    public function __construct(private readonly OperationalNotifier $alerts) {}

    public function apply(
        Product $product,
        Warehouse $warehouse,
        StockMovementType $type,
        string|int $quantity,
        ?string $unitCost,
        Model $source,
        User $actor,
        string $date,
        ?string $note = null,
        int $adjustmentSign = 1,
    ): StockMovement {
        return DB::transaction(function () use ($product, $warehouse, $type, $quantity, $unitCost, $source, $actor, $date, $note, $adjustmentSign): StockMovement {
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $quantity = Costing::quantity($quantity);

            if (Costing::compareQty($quantity, '0') !== 1) {
                throw new ApprovalStateException('The quantity must be greater than zero.');
            }

            $sign = $this->sign($type, $adjustmentSign);
            $signed = $sign === -1 ? Costing::quantity('-'.$quantity) : $quantity;
            $onHand = $this->onHand($product);
            $next = bcadd($onHand, $signed, 3);

            if (bccomp($next, '0.000', 3) === -1) {
                throw new ApprovalStateException('That movement would make stock negative.');
            }

            $cost = $unitCost === null
                ? Costing::cost((string) $product->average_cost)
                : Costing::cost($unitCost);

            $average = Costing::weightedAverage($onHand, (string) $product->average_cost, $signed, $cost);

            $movement = StockMovement::query()->create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'movement_type' => $type,
                'quantity' => $signed,
                'unit_cost' => $cost,
                'moved_on' => $date,
                'reference_type' => $source->getMorphClass(),
                'reference_id' => $source->getKey(),
                'note' => $note,
                'created_by' => $actor->id,
            ]);

            $product->average_cost = $average;
            $product->save();

            if (Costing::compareQty($next, (string) $product->reorder_level) !== 1) {
                $this->alerts->lowStock($product, $next);
            }

            return $movement;
        });
    }

    public function onHand(Product $product, ?int $warehouseId = null): string
    {
        $total = '0.000';

        $query = StockMovement::query()->where('product_id', $product->id);

        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        foreach ($query->pluck('quantity') as $quantity) {
            $total = bcadd($total, Costing::quantity((string) $quantity), 3);
        }

        return $total;
    }

    public function currentCost(Product $product): string
    {
        return Costing::cost((string) $product->average_cost);
    }

    public function isLow(Product $product): bool
    {
        return Costing::compareQty($this->onHand($product), (string) $product->reorder_level) !== 1;
    }

    private function sign(StockMovementType $type, int $adjustmentSign): int
    {
        if ($type === StockMovementType::Adjustment) {
            if (! in_array($adjustmentSign, [1, -1], true)) {
                throw new ApprovalStateException('An adjustment needs a direction.');
            }

            return $adjustmentSign;
        }

        return $type->increasesStock() ? 1 : -1;
    }
}
