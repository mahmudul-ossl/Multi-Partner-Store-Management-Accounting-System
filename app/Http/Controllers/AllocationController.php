<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AllocationMethod;
use App\Enums\PartnerStatus;
use App\Http\Requests\Accounting\AllocationRequest;
use App\Models\Partner;
use App\Models\ProfitAllocation;
use App\Services\Accounting\AllocationService;
use App\Services\Accounting\PeriodGuard;
use App\Support\Format;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AllocationController extends Controller
{
    public function index(PeriodGuard $periods): Response
    {
        $this->authorize('viewAny', ProfitAllocation::class);
        $allocations = ProfitAllocation::query()->with('approvalRequest')->latest('transaction_date')->paginate(20);
        $closed = $periods->closedThrough();

        return Inertia::render('Accounting/Allocations', [
            'allocations' => [
                'data' => $allocations->getCollection()->map(fn (ProfitAllocation $allocation): array => [
                    'id' => $allocation->id,
                    'reference' => $allocation->reference,
                    'date' => Format::date($allocation->transaction_date),
                    'method' => $allocation->method->label(),
                    'amount' => Money::of((string) $allocation->amount)->formatted(),
                    'status' => ['label' => $allocation->status->label(), 'tone' => $allocation->status->tone()],
                    'approval' => $allocation->approvalRequest ? $allocation->approvalRequest->completed_approvals.'/'.$allocation->approvalRequest->required_approvals : null,
                ])->all(),
                'current_page' => $allocations->currentPage(),
                'last_page' => $allocations->lastPage(),
                'from' => $allocations->firstItem(),
                'to' => $allocations->lastItem(),
                'total' => $allocations->total(),
                'per_page' => $allocations->perPage(),
            ],
            'methods' => AllocationMethod::options(),
            'partners' => Partner::query()->where('status', PartnerStatus::Active)->orderBy('name')->get(['id', 'name', 'ownership_percentage', 'investment_percentage']),
            'closed_through' => $closed === null ? null : Format::date($closed),
            'can' => ['create' => request()->user()?->can('create', ProfitAllocation::class) ?? false],
        ]);
    }

    public function store(AllocationRequest $request, AllocationService $allocations): RedirectResponse
    {
        $allocation = $allocations->create($request->user(), $request->validated());

        return redirect()
            ->route('accounting.allocations.show', $allocation)
            ->with('success', 'Profit allocation submitted for approval.');
    }

    public function show(ProfitAllocation $profitAllocation): Response
    {
        $this->authorize('view', $profitAllocation);
        $profitAllocation->load('lines.partner', 'approvalRequest');

        return Inertia::render('Accounting/AllocationShow', [
            'allocation' => [
                'id' => $profitAllocation->id,
                'reference' => $profitAllocation->reference,
                'date' => Format::date($profitAllocation->transaction_date),
                'method' => $profitAllocation->method->label(),
                'amount' => Money::of((string) $profitAllocation->amount)->formatted(),
                'note' => $profitAllocation->note,
                'status' => ['label' => $profitAllocation->status->label(), 'tone' => $profitAllocation->status->tone()],
                'approval' => $profitAllocation->approvalRequest ? [
                    'id' => $profitAllocation->approvalRequest->id,
                    'completed' => $profitAllocation->approvalRequest->completed_approvals,
                    'required' => $profitAllocation->approvalRequest->required_approvals,
                ] : null,
                'journal_entry_id' => $profitAllocation->journal_entry_id,
                'lines' => $profitAllocation->lines->map(fn ($line): array => [
                    'partner' => $line->partner?->name ?? '—',
                    'percentage' => (string) $line->percentage,
                    'amount' => Money::of((string) $line->amount)->formatted(),
                ])->all(),
            ],
        ]);
    }
}
