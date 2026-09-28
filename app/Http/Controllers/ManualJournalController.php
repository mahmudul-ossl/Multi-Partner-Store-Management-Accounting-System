<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Requests\Accounting\ManualJournalRequest;
use App\Http\Resources\ManualJournalResource;
use App\Models\ChartOfAccount;
use App\Models\FinancialAccount;
use App\Models\ManualJournal;
use App\Models\Partner;
use App\Services\Accounting\AccountingDirectory;
use App\Services\Accounting\AccountingPresenter;
use App\Services\Accounting\ManualJournalService;
use App\Support\PaginatorPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManualJournalController extends Controller
{
    public function index(Request $request, AccountingDirectory $directory): Response
    {
        $this->authorize('viewAny', ManualJournal::class);
        $filters = $request->only(['search', 'status']);

        return Inertia::render('Accounting/ManualJournals', [
            'journals' => PaginatorPayload::make(
                $directory->manualJournals($filters),
                ManualJournalResource::class,
            ),
            'filters' => [
                'search' => (string) ($filters['search'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
            ],
            'statuses' => DocumentStatus::options(),
            'charts' => $this->charts(),
            'financialAccounts' => $this->financialAccounts(),
            'partners' => Partner::query()->orderBy('name')->get(['id', 'name'])->map(fn (Partner $partner): array => [
                'id' => $partner->id,
                'name' => $partner->name,
            ])->all(),
        ]);
    }

    public function store(ManualJournalRequest $request, ManualJournalService $journals): RedirectResponse
    {
        $journal = $journals->create($request->user(), $request->validated());

        return redirect()->route('accounting.manual-journals.show', $journal)->with('success', 'Journal submitted for approval.');
    }

    public function show(ManualJournal $manualJournal, AccountingPresenter $presenter): Response
    {
        $this->authorize('view', $manualJournal);
        $manualJournal->load('lines.account', 'lines.partner', 'lines.financialAccount', 'approvalRequest', 'author');

        return Inertia::render('Accounting/ManualJournalShow', [
            'journal' => $presenter->manualJournal($manualJournal),
            'charts' => $this->charts(),
            'financialAccounts' => $this->financialAccounts(),
            'partners' => Partner::query()->orderBy('name')->get(['id', 'name'])->all(),
            'can' => [
                'update' => request()->user()?->can('update', $manualJournal) ?? false,
                'cancel' => request()->user()?->can('cancel', $manualJournal) ?? false,
            ],
        ]);
    }

    public function update(ManualJournalRequest $request, ManualJournal $manualJournal, ManualJournalService $journals): RedirectResponse
    {
        $journals->update($manualJournal, $request->user(), $request->validated());

        return redirect()->route('accounting.manual-journals.show', $manualJournal)->with('success', 'Journal updated.');
    }

    public function cancel(ManualJournal $manualJournal, ManualJournalService $journals): RedirectResponse
    {
        $this->authorize('cancel', $manualJournal);
        $journals->cancel($manualJournal, request()->user());

        return redirect()->route('accounting.manual-journals.show', $manualJournal)->with('success', 'Journal cancelled.');
    }

    /**
     * @return list<array{code: string, name: string, requires_financial_account: bool}>
     */
    private function charts(): array
    {
        return ChartOfAccount::query()
            ->where('is_active', true)
            ->withCount(['financialAccounts as active_financial_accounts_count' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('code')
            ->get()
            ->map(fn (ChartOfAccount $account): array => [
                'code' => $account->code,
                'name' => $account->code.' · '.$account->name,
                'requires_financial_account' => (int) $account->active_financial_accounts_count > 0,
            ])->all();
    }

    /**
     * @return list<array{id: int, name: string, chart_code: string}>
     */
    private function financialAccounts(): array
    {
        return FinancialAccount::query()
            ->with('chartOfAccount')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (FinancialAccount $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'chart_code' => $account->chartOfAccount?->code,
            ])->all();
    }
}
