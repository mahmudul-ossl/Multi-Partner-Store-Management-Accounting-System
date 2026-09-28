<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\NormalBalance;
use App\Http\Requests\Accounting\ChartAccountRequest;
use App\Models\ChartOfAccount;
use App\Services\Accounting\AccountingDirectory;
use App\Services\Accounting\AccountingPresenter;
use App\Services\Accounting\ChartOfAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ChartOfAccountController extends Controller
{
    public function index(AccountingDirectory $directory, AccountingPresenter $presenter): Response
    {
        $this->authorize('viewAny', ChartOfAccount::class);
        $accounts = $directory->chart();
        $byId = $accounts->keyBy('id');

        return Inertia::render('Accounting/Chart', [
            'accounts' => $accounts->map(function (ChartOfAccount $account) use ($presenter, $byId): array {
                return $presenter->chartAccount($account, $this->depth($account, $byId));
            })->values()->all(),
            'types' => $this->options(AccountType::cases()),
            'normalBalances' => $this->options(NormalBalance::cases()),
            'parents' => $accounts->map(fn (ChartOfAccount $account): array => [
                'id' => $account->id,
                'label' => $account->code.' · '.$account->name,
                'type' => $account->type->value,
            ])->values()->all(),
        ]);
    }

    public function store(ChartAccountRequest $request, ChartOfAccountService $accounts): RedirectResponse
    {
        $accounts->create($request->user(), $request->validated());

        return redirect()->route('accounting.chart.index')->with('success', 'Account added to the chart.');
    }

    public function update(ChartAccountRequest $request, ChartOfAccount $chartOfAccount, ChartOfAccountService $accounts): RedirectResponse
    {
        $accounts->update($chartOfAccount, $request->user(), $request->validated());

        return redirect()->route('accounting.chart.index')->with('success', 'Account updated.');
    }

    public function destroy(ChartOfAccount $chartOfAccount, ChartOfAccountService $accounts): RedirectResponse
    {
        $this->authorize('delete', $chartOfAccount);
        $accounts->delete($chartOfAccount, request()->user());

        return redirect()->route('accounting.chart.index')->with('success', 'Account removed.');
    }

    /**
     * @param  array<int, ChartOfAccount>|Collection<int, ChartOfAccount>  $byId
     */
    private function depth(ChartOfAccount $account, $byId): int
    {
        $level = 0;
        $parentId = $account->parent_id;
        $guard = 0;

        while ($parentId && $guard < 20) {
            $level++;
            $parent = $byId->get($parentId);
            $parentId = $parent?->parent_id;
            $guard++;
        }

        return $level;
    }

    /**
     * @param  list<\BackedEnum>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(
            fn (\BackedEnum $case): array => [
                'value' => (string) $case->value,
                'label' => method_exists($case, 'label') ? $case->label() : (string) $case->value,
            ],
            $cases,
        );
    }
}
