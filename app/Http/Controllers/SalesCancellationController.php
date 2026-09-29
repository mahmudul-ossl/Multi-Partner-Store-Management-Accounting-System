<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Sales\SalesCancellationRequest;
use App\Services\Sales\SalesCancellationService;
use Illuminate\Http\RedirectResponse;

class SalesCancellationController extends Controller
{
    public function store(SalesCancellationRequest $request, SalesCancellationService $cancellations): RedirectResponse
    {
        $cancellation = $cancellations->create($request->user(), $request->validated());

        return redirect()->route('sales.orders.show', $cancellation->sale_id)->with('success', 'Cancellation submitted for approval.');
    }
}
