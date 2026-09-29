<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\FinancialAccount;
use App\Services\Accounting\LedgerReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LedgerReportController extends Controller
{
    public function generalLedger(Request $request, LedgerReportService $reports): Response
    {
        $this->authorize('viewAny', ChartOfAccount::class);

        $accountId = $request->integer('account');
        $account = $accountId > 0 ? ChartOfAccount::query()->findOrFail($accountId) : null;
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        return Inertia::render('Accounting/GeneralLedger', [
            'accounts' => ChartOfAccount::query()->orderBy('code')->get(['id', 'code', 'name'])->map(fn (ChartOfAccount $row): array => [
                'id' => $row->id,
                'label' => $row->code.' · '.$row->name,
            ])->all(),
            'filters' => [
                'account' => $account?->id ? (string) $account->id : '',
                'from' => $from,
                'to' => $to,
            ],
            'report' => $account instanceof ChartOfAccount
                ? $reports->generalLedger($account, $from !== '' ? $from : null, $to !== '' ? $to : null)
                : null,
        ]);
    }

    public function cash(LedgerReportService $reports): Response
    {
        $this->authorize('viewAny', FinancialAccount::class);

        return Inertia::render('Accounting/CashReport', [
            'rows' => $reports->cashReport(),
        ]);
    }

    public function bank(LedgerReportService $reports): Response
    {
        $this->authorize('viewAny', FinancialAccount::class);

        return Inertia::render('Accounting/BankReport', [
            'report' => $reports->bankReport(),
        ]);
    }
}
