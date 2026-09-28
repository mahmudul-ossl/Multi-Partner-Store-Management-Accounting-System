<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\Sales\SaleRequest;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\SalesCancellation;
use App\Models\Warehouse;
use App\Services\Sales\SaleService;
use App\Services\Sales\SalesSettings;
use App\Support\Costing;
use App\Support\Format;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function index(SalesSettings $settings): Response
    {
        $this->authorize('viewAny', Sale::class);
        $sales = Sale::query()->with('customer', 'approvalRequest')->latest('transaction_date')->paginate(20);

        return Inertia::render('Sales/Orders', [
            'sales' => [
                'data' => $sales->getCollection()->map(fn (Sale $sale): array => [
                    'id' => $sale->id,
                    'reference' => $sale->reference,
                    'date' => Format::date($sale->transaction_date),
                    'customer' => $sale->customer?->name,
                    'total' => Money::of((string) $sale->total)->formatted(),
                    'paid' => Money::of((string) $sale->paid_amount)->formatted(),
                    'due' => Money::of((string) $sale->due_amount)->formatted(),
                    'status' => ['label' => $sale->status->label(), 'tone' => $sale->status->tone()],
                    'approval' => $sale->approvalRequest ? $sale->approvalRequest->completed_approvals.'/'.$sale->approvalRequest->required_approvals : null,
                ])->all(),
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'from' => $sales->firstItem(),
                'to' => $sales->lastItem(),
                'total' => $sales->total(),
                'per_page' => $sales->perPage(),
            ],
            'customers' => Customer::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'sku', 'selling_price']),
            'accounts' => FinancialAccount::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'methods' => $this->methods(),
            'discount_threshold' => Money::of($settings->threshold())->formatted(),
            'can' => ['create' => request()->user()?->can('create', Sale::class) ?? false],
        ]);
    }

    public function store(SaleRequest $request, SaleService $sales): RedirectResponse
    {
        $sale = $sales->create($request->user(), $request->validated());
        $message = $sale->status === DocumentStatus::Completed
            ? 'Sale completed.'
            : 'Large discount submitted for approval. Stock and the journal wait until it is approved.';

        return redirect()->route('sales.orders.show', $sale)->with('success', $message);
    }

    public function show(Sale $sale): Response
    {
        $this->authorize('view', $sale);
        $sale->load('customer', 'warehouse', 'items.product', 'approvalRequest', 'financialAccount', 'returns', 'refunds', 'payments');

        return Inertia::render('Sales/OrderShow', [
            'sale' => [
                'id' => $sale->id,
                'reference' => $sale->reference,
                'date' => Format::date($sale->transaction_date),
                'customer' => $sale->customer?->name,
                'customer_id' => $sale->customer_id,
                'warehouse' => $sale->warehouse?->name,
                'subtotal' => Money::of((string) $sale->subtotal)->formatted(),
                'discount' => Money::of((string) $sale->discount)->formatted(),
                'delivery' => Money::of((string) $sale->delivery)->formatted(),
                'total' => Money::of((string) $sale->total)->formatted(),
                'paid' => Money::of((string) $sale->paid_amount)->formatted(),
                'due' => Money::of((string) $sale->due_amount)->formatted(),
                'due_amount' => (string) $sale->due_amount,
                'account' => $sale->financialAccount?->name,
                'note' => $sale->note,
                'status' => ['label' => $sale->status->label(), 'tone' => $sale->status->tone(), 'value' => $sale->status->value],
                'journal_entry_id' => $sale->journal_entry_id,
                'completed' => $sale->status === DocumentStatus::Completed,
                'approval' => $sale->approvalRequest ? [
                    'id' => $sale->approvalRequest->id,
                    'completed' => $sale->approvalRequest->completed_approvals,
                    'required' => $sale->approvalRequest->required_approvals,
                ] : null,
                'items' => $sale->items->map(function ($item): array {
                    $returned = '0.000';
                    foreach (SaleReturnItem::query()->where('sale_item_id', $item->id)->pluck('quantity') as $quantity) {
                        $returned = bcadd($returned, Costing::quantity((string) $quantity), 3);
                    }

                    return [
                        'id' => $item->id,
                        'product' => $item->product?->name,
                        'quantity' => (string) $item->quantity,
                        'returned' => $returned,
                        'unit_price' => (string) $item->unit_price,
                        'unit_cost' => $item->unit_cost === null ? null : (string) $item->unit_cost,
                        'line_total' => Money::of((string) $item->line_subtotal)->formatted(),
                        'net' => Money::of((string) $item->net_amount)->formatted(),
                    ];
                })->all(),
                'returns' => $sale->returns->map(fn (SaleReturn $return): array => [
                    'id' => $return->id,
                    'reference' => $return->reference,
                    'total' => Money::of((string) $return->total)->formatted(),
                ])->all(),
                'refunds' => $sale->refunds->map(fn (Refund $refund): array => [
                    'reference' => $refund->reference,
                    'amount' => Money::of((string) $refund->amount)->formatted(),
                    'status' => $refund->status->label(),
                ])->all(),
                'payments' => $sale->payments->map(fn (CustomerPayment $payment): array => [
                    'reference' => $payment->reference,
                    'amount' => Money::of((string) $payment->amount)->formatted(),
                    'date' => Format::date($payment->payment_date),
                ])->all(),
            ],
            'accounts' => FinancialAccount::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'methods' => $this->methods(),
            'can' => [
                'return' => request()->user()?->can('create', SaleReturn::class) ?? false,
                'pay' => request()->user()?->can('create', CustomerPayment::class) ?? false,
                'refund' => request()->user()?->can('create', Refund::class) ?? false,
                'cancel' => request()->user()?->can('create', SalesCancellation::class) ?? false,
            ],
        ]);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function methods(): array
    {
        return array_map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => str($method->value)->headline()->toString()], PaymentMethod::cases());
    }
}
