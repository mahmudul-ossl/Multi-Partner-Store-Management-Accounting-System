<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Sales\SaleReturnRequest;
use App\Models\SaleReturn;
use App\Services\Sales\SaleReturnService;
use App\Support\Format;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SaleReturnController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', SaleReturn::class);

        return Inertia::render('Sales/Returns', [
            'returns' => SaleReturn::query()->with('customer', 'sale')->latest('transaction_date')->get()->map(fn (SaleReturn $return): array => [
                'id' => $return->id,
                'reference' => $return->reference,
                'sale' => $return->sale?->reference,
                'customer' => $return->customer?->name,
                'date' => Format::date($return->transaction_date),
                'total' => Money::of((string) $return->total)->formatted(),
                'status' => ['label' => $return->status->label(), 'tone' => $return->status->tone()],
            ])->all(),
        ]);
    }

    public function store(SaleReturnRequest $request, SaleReturnService $returns): RedirectResponse
    {
        $return = $returns->create($request->user(), $request->validated());

        return redirect()->route('sales.returns.show', $return)->with('success', 'Sales return posted.');
    }

    public function show(SaleReturn $saleReturn): Response
    {
        $this->authorize('view', $saleReturn);
        $saleReturn->load('items.product', 'customer', 'sale');

        return Inertia::render('Sales/ReturnShow', [
            'saleReturn' => [
                'reference' => $saleReturn->reference,
                'sale' => $saleReturn->sale?->reference,
                'sale_id' => $saleReturn->sale_id,
                'customer' => $saleReturn->customer?->name,
                'date' => Format::date($saleReturn->transaction_date),
                'total' => Money::of((string) $saleReturn->total)->formatted(),
                'cogs' => Money::of((string) $saleReturn->cogs_total)->formatted(),
                'note' => $saleReturn->note,
                'status' => ['label' => $saleReturn->status->label(), 'tone' => $saleReturn->status->tone()],
                'journal_entry_id' => $saleReturn->journal_entry_id,
                'items' => $saleReturn->items->map(fn ($item): array => [
                    'product' => $item->product?->name,
                    'quantity' => (string) $item->quantity,
                    'line_total' => Money::of((string) $item->line_total)->formatted(),
                    'unit_cost' => (string) $item->unit_cost,
                ])->all(),
            ],
        ]);
    }
}
