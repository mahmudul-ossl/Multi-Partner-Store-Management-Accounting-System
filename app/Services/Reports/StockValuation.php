<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Inventory\InventoryService;
use App\Support\Costing;
use App\Support\Money;

/**
 * Inventory value from stock movements and the weighted-average cost.
 */
final class StockValuation
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function current(): string
    {
        $total = '0.00';

        foreach (Product::query()->orderBy('id')->get() as $product) {
            $total = Money::of($total)->add(Costing::lineTotal(
                $this->inventory->onHand($product),
                (string) $product->average_cost,
            ))->amount();
        }

        return $total;
    }

    public function asOf(string $date): string
    {
        $total = '0.00';

        foreach (Product::query()->orderBy('id')->get() as $product) {
            $position = $this->position($product, $date);
            $total = Money::of($total)->add(Costing::lineTotal($position['quantity'], $position['average']))->amount();
        }

        return $total;
    }

    /**
     * @return array{quantity: string, average: string}
     */
    public function position(Product $product, string $date): array
    {
        $onHand = '0.000';
        $average = '0.0000';
        $movements = StockMovement::query()
            ->where('product_id', $product->id)
            ->whereDate('moved_on', '<=', $date)
            ->orderBy('id')
            ->get(['quantity', 'unit_cost']);

        foreach ($movements as $movement) {
            $signed = Costing::quantity((string) $movement->quantity);
            $average = Costing::weightedAverage($onHand, $average, $signed, (string) $movement->unit_cost);
            $onHand = bcadd($onHand, $signed, 3);
        }

        return ['quantity' => $onHand, 'average' => $average];
    }
}
