<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Sales\RefundRequest;
use App\Services\Sales\RefundService;
use Illuminate\Http\RedirectResponse;

class RefundController extends Controller
{
    public function store(RefundRequest $request, RefundService $refunds): RedirectResponse
    {
        $refund = $refunds->create($request->user(), $request->validated());

        return redirect()->route('sales.orders.show', $refund->sale_id)->with('success', 'Refund submitted for approval.');
    }
}
