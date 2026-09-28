<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\Sales\CustomerRequest;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\FinancialAccount;
use App\Models\Sale;
use App\Services\Sales\CustomerService;
use App\Support\Format;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(CustomerService $customers): Response
    {
        $this->authorize('viewAny', Customer::class);

        return Inertia::render('Sales/Customers', [
            'customers' => $customers->dues(),
            'can' => ['manage' => request()->user()?->can('create', Customer::class) ?? false],
        ]);
    }

    public function store(CustomerRequest $request, CustomerService $customers): RedirectResponse
    {
        $customer = $customers->create($request->user(), $request->validated());

        return redirect()->route('sales.customers.show', $customer)->with('success', 'Customer saved.');
    }

    public function show(Customer $customer): Response
    {
        $this->authorize('view', $customer);
        $sales = Sale::query()->where('customer_id', $customer->id)->latest('transaction_date')->get();

        return Inertia::render('Sales/CustomerShow', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'address' => $customer->address,
                'notes' => $customer->notes,
                'status' => $customer->status->label(),
            ],
            'sales' => $sales->map(fn (Sale $sale): array => [
                'id' => $sale->id,
                'reference' => $sale->reference,
                'date' => Format::date($sale->transaction_date),
                'total' => Money::of((string) $sale->total)->formatted(),
                'due' => Money::of((string) $sale->due_amount)->formatted(),
                'status' => ['label' => $sale->status->label(), 'tone' => $sale->status->tone()],
            ])->all(),
            'accounts' => FinancialAccount::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'methods' => array_map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => str($method->value)->headline()->toString()], PaymentMethod::cases()),
            'can' => ['pay' => request()->user()?->can('create', CustomerPayment::class) ?? false],
            'open_sales' => $sales->where('status', DocumentStatus::Completed)->filter(fn (Sale $sale): bool => Money::of((string) $sale->due_amount)->compare('0.00') === 1)->map(fn (Sale $sale): array => [
                'id' => $sale->id,
                'reference' => $sale->reference,
                'due_amount' => (string) $sale->due_amount,
            ])->values()->all(),
        ]);
    }
}
