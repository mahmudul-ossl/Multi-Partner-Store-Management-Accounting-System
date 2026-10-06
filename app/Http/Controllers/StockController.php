<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Inventory\StockQuery;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function index(Request $request, StockQuery $stock): Response
    {
        $this->authorize('viewAny', StockMovement::class);
        $rows = $stock->stock($request->integer('warehouse') ?: null);
        $value = '0.00';

        foreach ($rows as $row) {
            $value = Money::of($value)->add($row['value_amount'])->amount();
        }

        return Inertia::render('Inventory/Stock', [
            'rows' => $rows,
            'inventory_value' => Money::of($value)->formatted(),
            'filters' => ['warehouse' => (string) $request->string('warehouse')],
        ]);
    }

    public function movements(Request $request, StockQuery $stock): Response
    {
        $this->authorize('viewAny', StockMovement::class);
        $filters = $request->only(['product_id', 'type', 'from', 'to']);
        $page = $stock->movements($filters);

        return Inertia::render('Inventory/Movements', [
            'movements' => [
                'data' => collect($page->items())->map(fn (StockMovement $movement): array => $stock->presentMovement($movement))->all(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
                'total' => $page->total(),
                'per_page' => $page->perPage(),
            ],
            'filters' => [
                'product_id' => (string) ($filters['product_id'] ?? ''),
                'type' => (string) ($filters['type'] ?? ''),
                'from' => (string) ($filters['from'] ?? ''),
                'to' => (string) ($filters['to'] ?? ''),
            ],
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'sku']),
            'types' => StockMovementType::options(),
        ]);
    }

    public function low(StockQuery $stock): Response
    {
        $this->authorize('viewAny', StockMovement::class);

        return Inertia::render('Inventory/LowStock', [
            'rows' => $stock->lowStock(),
        ]);
    }
}
