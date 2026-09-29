<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\DocumentStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Support\Costing;
use App\Support\Format;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class StockQuery
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function stock(?int $warehouseId = null): array
    {
        return Product::query()
            ->with('category', 'unit')
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($warehouseId): array {
                $onHand = $this->inventory->onHand($product, $warehouseId);
                $value = Costing::lineTotal($onHand, (string) $product->average_cost);

                return [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'category' => $product->category?->name,
                    'on_hand' => $onHand,
                    'average_cost' => Costing::cost((string) $product->average_cost),
                    'value' => Money::of($value)->formatted(),
                    'value_amount' => $value,
                    'reorder_level' => Costing::quantity((string) $product->reorder_level),
                    'low' => Costing::compareQty($onHand, (string) $product->reorder_level) !== 1,
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lowStock(): array
    {
        return array_values(array_filter($this->stock(), fn (array $row): bool => $row['low']));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function movements(array $filters): LengthAwarePaginator
    {
        return StockMovement::query()
            ->with('product', 'warehouse', 'author')
            ->when(($filters['product_id'] ?? '') !== '', fn (Builder $query) => $query->where('product_id', $filters['product_id']))
            ->when(($filters['type'] ?? '') !== '', fn (Builder $query) => $query->where('movement_type', $filters['type']))
            ->when(($filters['from'] ?? '') !== '', fn (Builder $query) => $query->whereDate('moved_on', '>=', $filters['from']))
            ->when(($filters['to'] ?? '') !== '', fn (Builder $query) => $query->whereDate('moved_on', '<=', $filters['to']))
            ->orderByDesc('moved_on')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function supplierDues(): array
    {
        return Supplier::query()->orderBy('name')->get()->map(function (Supplier $supplier): array {
            $due = '0.00';

            foreach (Purchase::query()->where('supplier_id', $supplier->id)->where('status', DocumentStatus::Approved)->pluck('due_amount') as $amount) {
                $due = Money::of($due)->add((string) $amount)->amount();
            }

            return [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'company_name' => $supplier->company_name,
                'due' => Money::of($due)->formatted(),
                'due_amount' => $due,
            ];
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function presentMovement(StockMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'date' => Format::date($movement->moved_on),
            'product' => $movement->product?->name,
            'sku' => $movement->product?->sku,
            'warehouse' => $movement->warehouse?->name,
            'type' => $movement->movement_type->label(),
            'quantity' => Costing::quantity((string) $movement->quantity),
            'unit_cost' => Costing::cost((string) $movement->unit_cost),
            'note' => $movement->note,
            'author' => $movement->author?->name,
        ];
    }
}
