<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\Inventory\SupplierRequest;
use App\Models\FinancialAccount;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\Inventory\CatalogService;
use App\Services\Inventory\StockQuery;
use App\Support\Format;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(StockQuery $stock): Response
    {
        $this->authorize('viewAny', Supplier::class);

        return Inertia::render('Inventory/Suppliers', [
            'suppliers' => $stock->supplierDues(),
        ]);
    }

    public function store(SupplierRequest $request, CatalogService $catalog): RedirectResponse
    {
        $supplier = $catalog->createSupplier($request->user(), $request->validated());

        return redirect()->route('inventory.suppliers.show', $supplier)->with('success', 'Supplier saved.');
    }

    public function show(Supplier $supplier): Response
    {
        $this->authorize('view', $supplier);
        $supplier->load('contacts');

        $purchases = Purchase::query()->where('supplier_id', $supplier->id)->latest('transaction_date')->get();
        $payments = SupplierPayment::query()->where('supplier_id', $supplier->id)->latest('payment_date')->get();

        return Inertia::render('Inventory/SupplierShow', [
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'company_name' => $supplier->company_name,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'address' => $supplier->address,
                'tax_id' => $supplier->tax_id,
                'notes' => $supplier->notes,
                'status' => $supplier->status->label(),
                'contacts' => $supplier->contacts->map(fn ($contact): array => [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'phone' => $contact->phone,
                    'email' => $contact->email,
                    'role' => $contact->role,
                ])->all(),
            ],
            'purchases' => $purchases->map(fn (Purchase $purchase): array => [
                'id' => $purchase->id,
                'reference' => $purchase->reference,
                'date' => Format::date($purchase->transaction_date),
                'total' => Money::of((string) $purchase->total)->formatted(),
                'due' => Money::of((string) $purchase->due_amount)->formatted(),
                'due_amount' => (string) $purchase->due_amount,
                'status' => $purchase->status->label(),
                'approved' => $purchase->status === DocumentStatus::Approved,
            ])->all(),
            'payments' => $payments->map(fn (SupplierPayment $payment): array => [
                'id' => $payment->id,
                'reference' => $payment->reference,
                'date' => Format::date($payment->payment_date),
                'amount' => Money::of((string) $payment->amount)->formatted(),
                'status' => $payment->status->label(),
            ])->all(),
            'accounts' => FinancialAccount::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'methods' => array_map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => str($method->value)->headline()->toString()], PaymentMethod::cases()),
            'can' => [
                'pay' => request()->user()?->can('create', SupplierPayment::class) ?? false,
                'contact' => request()->user()?->can('create', Supplier::class) ?? false,
            ],
        ]);
    }

    public function storeContact(Request $request, Supplier $supplier, CatalogService $catalog): RedirectResponse
    {
        $this->authorize('create', Supplier::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'role' => ['nullable', 'string', 'max:100'],
        ]);
        $catalog->addContact($supplier, $request->user(), $data);

        return back()->with('success', 'Contact added.');
    }
}
