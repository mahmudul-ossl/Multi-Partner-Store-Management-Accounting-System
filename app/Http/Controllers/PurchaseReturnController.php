<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\PurchaseReturnRequest;
use App\Models\PurchaseReturn;
use App\Services\Purchasing\PurchaseReturnService;
use App\Support\Format;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseReturnController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', PurchaseReturn::class);

        return Inertia::render('Inventory/Returns', [
            'returns' => PurchaseReturn::query()->with('supplier', 'purchase')->latest('transaction_date')->get()->map(fn (PurchaseReturn $return): array => [
                'id' => $return->id,
                'reference' => $return->reference,
                'purchase' => $return->purchase?->reference,
                'supplier' => $return->supplier?->name,
                'date' => Format::date($return->transaction_date),
                'total' => Money::of((string) $return->total)->formatted(),
                'status' => ['label' => $return->status->label(), 'tone' => $return->status->tone()],
            ])->all(),
        ]);
    }

    public function store(PurchaseReturnRequest $request, PurchaseReturnService $returns): RedirectResponse
    {
        $return = $returns->create($request->user(), $request->validated());

        return redirect()->route('inventory.returns.show', $return)->with('success', 'Return submitted for approval.');
    }

    public function show(PurchaseReturn $purchaseReturn): Response
    {
        $this->authorize('view', $purchaseReturn);
        $purchaseReturn->load('items.product', 'supplier', 'purchase', 'approvalRequest');

        return Inertia::render('Inventory/ReturnShow', [
            'purchaseReturn' => [
                'id' => $purchaseReturn->id,
                'reference' => $purchaseReturn->reference,
                'purchase' => $purchaseReturn->purchase?->reference,
                'purchase_id' => $purchaseReturn->purchase_id,
                'supplier' => $purchaseReturn->supplier?->name,
                'date' => Format::date($purchaseReturn->transaction_date),
                'total' => Money::of((string) $purchaseReturn->total)->formatted(),
                'note' => $purchaseReturn->note,
                'status' => ['label' => $purchaseReturn->status->label(), 'tone' => $purchaseReturn->status->tone()],
                'journal_entry_id' => $purchaseReturn->journal_entry_id,
                'approval_id' => $purchaseReturn->approvalRequest?->id,
                'items' => $purchaseReturn->items->map(fn ($item): array => [
                    'product' => $item->product?->name,
                    'quantity' => (string) $item->quantity,
                    'line_total' => Money::of((string) $item->line_total)->formatted(),
                ])->all(),
            ],
        ]);
    }
}
