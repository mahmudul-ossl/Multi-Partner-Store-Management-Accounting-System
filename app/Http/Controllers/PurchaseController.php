<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\Inventory\PurchaseRequest;
use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Services\Purchasing\PurchaseService;
use App\Support\Format;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Purchase::class);

        $purchases = Purchase::query()->with('supplier', 'approvalRequest')->latest('transaction_date')->paginate(20);

        return Inertia::render('Inventory/Purchases', [
            'purchases' => [
                'data' => $purchases->getCollection()->map(fn (Purchase $purchase): array => [
                    'id' => $purchase->id,
                    'reference' => $purchase->reference,
                    'date' => Format::date($purchase->transaction_date),
                    'supplier' => $purchase->supplier?->name,
                    'total' => Money::of((string) $purchase->total)->formatted(),
                    'paid' => Money::of((string) $purchase->paid_amount)->formatted(),
                    'due' => Money::of((string) $purchase->due_amount)->formatted(),
                    'status' => ['label' => $purchase->status->label(), 'tone' => $purchase->status->tone()],
                    'approval' => $purchase->approvalRequest ? $purchase->approvalRequest->completed_approvals.'/'.$purchase->approvalRequest->required_approvals : null,
                ])->all(),
                'current_page' => $purchases->currentPage(),
                'last_page' => $purchases->lastPage(),
                'from' => $purchases->firstItem(),
                'to' => $purchases->lastItem(),
                'total' => $purchases->total(),
                'per_page' => $purchases->perPage(),
            ],
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'sku']),
            'accounts' => FinancialAccount::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'type']),
            'methods' => array_map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => str($method->value)->headline()->toString()], PaymentMethod::cases()),
            'can' => ['create' => request()->user()?->can('create', Purchase::class) ?? false],
        ]);
    }

    public function store(PurchaseRequest $request, PurchaseService $purchases): RedirectResponse
    {
        $purchase = $purchases->create($request->user(), $request->validated());

        return redirect()->route('inventory.purchases.show', $purchase)->with('success', 'Purchase submitted for approval.');
    }

    public function show(Purchase $purchase): Response
    {
        $this->authorize('view', $purchase);
        $purchase->load('supplier', 'items.product', 'approvalRequest', 'financialAccount');

        $canUpdate = request()->user()?->can('update', $purchase) ?? false;

        return Inertia::render('Inventory/PurchaseShow', [
            'purchase' => [
                'id' => $purchase->id,
                'reference' => $purchase->reference,
                'date' => Format::date($purchase->transaction_date),
                'transaction_date' => $purchase->transaction_date?->toDateString(),
                'supplier_id' => $purchase->supplier_id,
                'supplier' => $purchase->supplier?->name,
                'total' => Money::of((string) $purchase->total)->formatted(),
                'paid' => Money::of((string) $purchase->paid_amount)->formatted(),
                'paid_amount' => (string) $purchase->paid_amount,
                'due' => Money::of((string) $purchase->due_amount)->formatted(),
                'payment_method' => $purchase->payment_method?->value,
                'financial_account_id' => $purchase->financial_account_id,
                'account' => $purchase->financialAccount?->name,
                'note' => $purchase->note,
                'status' => ['label' => $purchase->status->label(), 'tone' => $purchase->status->tone(), 'value' => $purchase->status->value],
                'journal_entry_id' => $purchase->journal_entry_id,
                'approval' => $purchase->approvalRequest ? [
                    'id' => $purchase->approvalRequest->id,
                    'completed' => $purchase->approvalRequest->completed_approvals,
                    'required' => $purchase->approvalRequest->required_approvals,
                ] : null,
                'approved' => $purchase->status === DocumentStatus::Approved,
                'items' => $purchase->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product' => $item->product?->name,
                    'quantity' => (string) $item->quantity,
                    'unit_cost' => (string) $item->unit_cost,
                    'line_total' => Money::of((string) $item->line_total)->formatted(),
                ])->all(),
            ],
            'suppliers' => $canUpdate ? Supplier::query()->orderBy('name')->get(['id', 'name']) : [],
            'products' => $canUpdate ? Product::query()->orderBy('name')->get(['id', 'name', 'sku']) : [],
            'accounts' => $canUpdate ? FinancialAccount::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'type']) : [],
            'methods' => $canUpdate
                ? array_map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => str($method->value)->headline()->toString()], PaymentMethod::cases())
                : [],
            'can' => [
                'return' => request()->user()?->can('create', PurchaseReturn::class) ?? false,
                'update' => $canUpdate,
            ],
        ]);
    }

    public function update(PurchaseRequest $request, Purchase $purchase, PurchaseService $purchases): RedirectResponse
    {
        $purchases->update($purchase, $request->user(), $request->validated());

        return redirect()->route('inventory.purchases.show', $purchase)->with('success', 'Purchase updated.');
    }
}
