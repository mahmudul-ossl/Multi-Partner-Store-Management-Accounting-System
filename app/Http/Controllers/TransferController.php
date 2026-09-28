<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Requests\Finance\TransferRequest;
use App\Http\Resources\TransferResource;
use App\Models\Partner;
use App\Models\PartnerTransfer;
use App\Models\User;
use App\Services\Finance\FinanceDirectory;
use App\Services\Finance\FinancePresenter;
use App\Services\Finance\TransferService;
use App\Services\PartnerDirectory;
use App\Support\PaginatorPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    public function index(Request $request, FinanceDirectory $directory, PartnerDirectory $partners): Response
    {
        $this->authorize('viewAny', PartnerTransfer::class);
        $filters = $request->only(['search', 'status', 'sort', 'direction']);

        return Inertia::render('Transfers/Index', [
            'transfers' => PaginatorPayload::make($directory->transfers($request->user(), $filters), TransferResource::class),
            'filters' => [
                'search' => (string) ($filters['search'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
                'sort' => (string) ($filters['sort'] ?? 'transaction_date'),
                'direction' => (string) ($filters['direction'] ?? 'desc'),
            ],
            'statuses' => DocumentStatus::options(),
            'partners' => $this->partnerOptions($partners, $request->user()),
        ]);
    }

    public function store(TransferRequest $request, TransferService $transfers): RedirectResponse
    {
        $transfer = $transfers->create($request->user(), $request->validated());

        return redirect()->route('transfers.show', $transfer)->with('success', 'Transfer submitted for approval.');
    }

    public function show(PartnerTransfer $transfer, FinancePresenter $presenter, PartnerDirectory $partners): Response
    {
        $this->authorize('view', $transfer);

        return Inertia::render('Transfers/Show', [
            'transfer' => $presenter->transfer($transfer),
            'partners' => $this->partnerOptions($partners, request()->user()),
            'can' => [
                'update' => request()->user()?->can('update', $transfer) ?? false,
                'cancel' => request()->user()?->can('cancel', $transfer) ?? false,
                'reverse' => request()->user()?->can('reverse', $transfer) ?? false,
            ],
        ]);
    }

    public function update(TransferRequest $request, PartnerTransfer $transfer, TransferService $transfers): RedirectResponse
    {
        $transfers->update($transfer, $request->user(), $request->validated());

        return redirect()->route('transfers.show', $transfer)->with('success', 'Transfer updated.');
    }

    public function cancel(PartnerTransfer $transfer, TransferService $transfers): RedirectResponse
    {
        $this->authorize('cancel', $transfer);
        $transfers->cancel($transfer, request()->user());

        return redirect()->route('transfers.show', $transfer)->with('success', 'Transfer cancelled.');
    }

    public function reverse(Request $request, PartnerTransfer $transfer, TransferService $transfers): RedirectResponse
    {
        $this->authorize('reverse', $transfer);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        $transfers->reverse($transfer, $request->user(), $data['reason']);

        return redirect()->route('transfers.show', $transfer)->with('success', 'Transfer reversed.');
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function partnerOptions(PartnerDirectory $partners, User $user): array
    {
        return $partners->visibleTo($user)->orderBy('name')->get(['id', 'name', 'partner_code'])
            ->map(fn (Partner $partner): array => [
                'id' => $partner->id,
                'name' => $partner->partner_code.' · '.$partner->name,
            ])->all();
    }
}
