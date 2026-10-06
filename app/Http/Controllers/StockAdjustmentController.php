<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StockAdjustmentKind;
use App\Http\Requests\Inventory\StockAdjustmentRequest;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use App\Services\Inventory\StockAdjustmentService;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StockAdjustmentController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', StockAdjustment::class);

        return Inertia::render('Inventory/Adjustments', [
            'adjustments' => StockAdjustment::query()->with('product', 'warehouse', 'approvalRequest')->latest('transaction_date')->limit(50)->get()->map(fn (StockAdjustment $adjustment): array => [
                'id' => $adjustment->id,
                'reference' => $adjustment->reference,
                'kind' => $adjustment->kind->label(),
                'product' => $adjustment->product?->name,
                'warehouse' => $adjustment->warehouse?->name,
                'quantity' => (string) $adjustment->quantity,
                'date' => Format::date($adjustment->transaction_date),
                'reason' => $adjustment->reason,
                'status' => ['label' => $adjustment->status->label(), 'tone' => $adjustment->status->tone()],
            ])->all(),
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'sku']),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'kinds' => StockAdjustmentKind::options(),
            'can' => ['create' => request()->user()?->can('create', StockAdjustment::class) ?? false],
        ]);
    }

    public function store(StockAdjustmentRequest $request, StockAdjustmentService $adjustments): RedirectResponse
    {
        $adjustments->create($request->user(), $request->validated());

        return redirect()->route('inventory.adjustments.index')->with('success', 'Stock document submitted for approval.');
    }
}
