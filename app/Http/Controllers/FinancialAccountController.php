<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\FinancialAccountType;
use App\Http\Requests\Accounting\StoreFinancialAccountRequest;
use App\Http\Requests\Accounting\UpdateFinancialAccountRequest;
use App\Http\Resources\FinancialAccountResource;
use App\Models\ChartOfAccount;
use App\Models\FinancialAccount;
use App\Models\JournalEntryLine;
use App\Services\Accounting\AccountingDirectory;
use App\Services\Accounting\AccountingPresenter;
use App\Services\Accounting\FinancialAccountService;
use App\Support\Format;
use App\Support\PaginatorPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinancialAccountController extends Controller
{
    public function index(Request $request, AccountingDirectory $directory): Response
    {
        $this->authorize('viewAny', FinancialAccount::class);
        $filters = $request->only(['search', 'type']);

        return Inertia::render('Accounting/Accounts', [
            'accounts' => PaginatorPayload::make(
                $directory->financialAccounts($filters),
                FinancialAccountResource::class,
            ),
            'filters' => [
                'search' => (string) ($filters['search'] ?? ''),
                'type' => (string) ($filters['type'] ?? ''),
            ],
            'types' => $this->types(),
            'charts' => $this->assetCharts(),
        ]);
    }

    public function store(StoreFinancialAccountRequest $request, FinancialAccountService $accounts): RedirectResponse
    {
        $account = $accounts->create($request->user(), $request->validated());

        return redirect()->route('accounting.accounts.show', $account)->with('success', 'Financial account opened.');
    }

    public function show(FinancialAccount $financialAccount, AccountingPresenter $presenter, FinancialAccountService $accounts): Response
    {
        $this->authorize('view', $financialAccount);
        $financialAccount->load('chartOfAccount');

        $lines = JournalEntryLine::query()
            ->where('financial_account_id', $financialAccount->id)
            ->with('entry')
            ->latest('id')
            ->limit(25)
            ->get()
            ->map(fn (JournalEntryLine $line): array => [
                'id' => $line->id,
                'date' => Format::date($line->entry?->entry_date),
                'reference' => $line->entry?->reference,
                'journal_id' => $line->journal_entry_id,
                'description' => $line->description,
                'debit' => $presenter->money((string) $line->debit),
                'credit' => $presenter->money((string) $line->credit),
            ])->all();

        return Inertia::render('Accounting/AccountShow', [
            'account' => $presenter->financialAccount($financialAccount, true),
            'lines' => $lines,
            'can' => [
                'update' => request()->user()?->can('update', $financialAccount) ?? false,
                'reconcile' => request()->user()?->can('reconcile', $financialAccount) ?? false,
            ],
        ]);
    }

    public function update(UpdateFinancialAccountRequest $request, FinancialAccount $financialAccount, FinancialAccountService $accounts): RedirectResponse
    {
        $accounts->update($financialAccount, $request->user(), $request->validated());

        return redirect()->route('accounting.accounts.show', $financialAccount)->with('success', 'Financial account updated.');
    }

    public function reconcile(FinancialAccount $financialAccount, FinancialAccountService $accounts): RedirectResponse
    {
        $this->authorize('reconcile', $financialAccount);
        $accounts->syncFromLedger($financialAccount, request()->user());

        return redirect()->route('accounting.accounts.show', $financialAccount)->with('success', 'Balance replaced from the ledger.');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function types(): array
    {
        return array_map(
            fn (FinancialAccountType $type): array => ['value' => $type->value, 'label' => $type->label()],
            FinancialAccountType::cases(),
        );
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function assetCharts(): array
    {
        return ChartOfAccount::query()
            ->where('type', AccountType::Asset)
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(fn (ChartOfAccount $account): array => [
                'id' => $account->id,
                'label' => $account->code.' · '.$account->name,
            ])->all();
    }
}
