<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Http\Requests\Accounting\PeriodCloseRequest;
use App\Models\AccountingPeriod;
use App\Services\Accounting\PeriodGuard;
use App\Services\Accounting\PeriodService;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PeriodController extends Controller
{
    public function index(PeriodGuard $periods): Response
    {
        abort_unless(request()->user()?->can(PermissionName::AccountingView->value), 403);
        $closed = $periods->closedThrough();

        return Inertia::render('Accounting/Periods', [
            'closed_through' => $closed === null ? null : Format::date($closed),
            'periods' => AccountingPeriod::query()->with('closedBy')->orderByDesc('closed_through')->get()->map(fn (AccountingPeriod $period): array => [
                'id' => $period->id,
                'closed_through' => Format::date($period->closed_through),
                'note' => $period->note,
                'closed_by' => $period->closedBy?->name,
            ])->all(),
            'can' => ['close' => request()->user()?->can(PermissionName::AccountingManage->value) ?? false],
        ]);
    }

    public function store(PeriodCloseRequest $request, PeriodService $periods): RedirectResponse
    {
        $periods->close(
            $request->user(),
            $request->date('closed_through')->toDateString(),
            $request->validated('note'),
        );

        return redirect()->route('accounting.periods.index')->with('success', 'Books closed through that date.');
    }
}
