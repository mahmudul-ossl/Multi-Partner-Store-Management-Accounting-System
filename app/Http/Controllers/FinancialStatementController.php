<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Exceptions\ApprovalStateException;
use App\Services\Accounting\FinancialStatementService;
use App\Support\ReportWindow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinancialStatementController extends Controller
{
    public function profitLoss(Request $request, FinancialStatementService $statements): Response
    {
        abort_unless($request->user()?->can(PermissionName::ProfitLossView->value), 403);

        $period = $request->string('period')->toString() ?: 'this_month';
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        [$start, $end] = ReportWindow::resolve($period, $from !== '' ? $from : null, $to !== '' ? $to : null);

        return Inertia::render('Accounting/ProfitLoss', [
            'filters' => [
                'period' => $period,
                'from' => $from,
                'to' => $to,
            ],
            'periods' => [
                ['value' => 'today', 'label' => 'Today'],
                ['value' => 'this_week', 'label' => 'This week'],
                ['value' => 'this_month', 'label' => 'This month'],
                ['value' => 'previous_month', 'label' => 'Previous month'],
                ['value' => 'custom', 'label' => 'Custom range'],
            ],
            'report' => $statements->profitAndLoss($start, $end),
        ]);
    }

    public function balanceSheet(Request $request, FinancialStatementService $statements): Response
    {
        abort_unless($request->user()?->can(PermissionName::BalanceSheetView->value), 403);

        return Inertia::render('Accounting/BalanceSheet', [
            'filters' => ['as_of' => $this->asOf($request)],
            'report' => $statements->balanceSheet($this->asOf($request)),
        ]);
    }

    public function trialBalance(Request $request, FinancialStatementService $statements): Response
    {
        abort_unless($request->user()?->can(PermissionName::TrialBalanceView->value), 403);

        return Inertia::render('Accounting/TrialBalance', [
            'filters' => ['as_of' => $this->asOf($request)],
            'report' => $statements->trialBalance($this->asOf($request)),
        ]);
    }

    private function asOf(Request $request): string
    {
        $date = $request->string('as_of')->toString() ?: now()->toDateString();

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new ApprovalStateException('Choose a statement date.');
        }

        return $date;
    }
}
