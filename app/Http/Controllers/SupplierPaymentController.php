<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\SupplierPaymentRequest;
use App\Services\Purchasing\SupplierPaymentService;
use Illuminate\Http\RedirectResponse;

class SupplierPaymentController extends Controller
{
    public function store(SupplierPaymentRequest $request, SupplierPaymentService $payments): RedirectResponse
    {
        $payment = $payments->create($request->user(), $request->validated());

        return redirect()->route('inventory.suppliers.show', $payment->supplier_id)->with('success', 'Supplier payment submitted for approval.');
    }
}
