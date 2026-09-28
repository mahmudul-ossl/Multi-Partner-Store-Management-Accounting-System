<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Sales\CustomerPaymentRequest;
use App\Services\Sales\CustomerPaymentService;
use Illuminate\Http\RedirectResponse;

class CustomerPaymentController extends Controller
{
    public function store(CustomerPaymentRequest $request, CustomerPaymentService $payments): RedirectResponse
    {
        $payment = $payments->create($request->user(), $request->validated());

        return redirect()->route('sales.orders.show', $payment->sale_id)->with('success', 'Customer payment posted.');
    }
}
