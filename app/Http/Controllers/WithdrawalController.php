<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\Finance\WithdrawalRequest;
use App\Http\Resources\WithdrawalResource;
use App\Models\FinancialAccount;
use App\Models\Partner;
use App\Models\PartnerWithdrawal;
use App\Models\User;
use App\Services\Finance\FinanceDirectory;
use App\Services\Finance\FinancePresenter;
use App\Services\Finance\WithdrawalService;
use App\Services\PartnerDirectory;
use App\Support\PaginatorPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WithdrawalController extends Controller
{
    public function index(Request $request, FinanceDirectory $directory, PartnerDirectory $partners): Response
    {
        $this->authorize('viewAny', PartnerWithdrawal::class);
        $filters = $request->only(['search', 'status', 'sort', 'direction']);

        return Inertia::render('Withdrawals/Index', [
            'withdrawals' => PaginatorPayload::make($directory->withdrawals($request->user(), $filters), WithdrawalResource::class),
            'filters' => [
                'search' => (string) ($filters['search'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
                'sort' => (string) ($filters['sort'] ?? 'transaction_date'),
                'direction' => (string) ($filters['direction'] ?? 'desc'),
            ],
            'statuses' => DocumentStatus::options(),
            'paymentMethods' => $this->paymentMethods(),
            'partners' => $this->partnerOptions($partners, $request->user()),
            'accounts' => $this->accounts(),
        ]);
    }

    public function store(WithdrawalRequest $request, WithdrawalService $withdrawals): RedirectResponse
    {
        $withdrawal = $withdrawals->create($request->user(), $request->validated());

        return redirect()->route('withdrawals.show', $withdrawal)->with('success', 'Withdrawal submitted for approval.');
    }

    public function show(PartnerWithdrawal $withdrawal, FinancePresenter $presenter, PartnerDirectory $partners): Response
    {
        $this->authorize('view', $withdrawal);

        return Inertia::render('Withdrawals/Show', [
            'withdrawal' => $presenter->withdrawal($withdrawal),
            'paymentMethods' => $this->paymentMethods(),
            'partners' => $this->partnerOptions($partners, request()->user()),
            'accounts' => $this->accounts(),
            'can' => [
                'update' => request()->user()?->can('update', $withdrawal) ?? false,
                'cancel' => request()->user()?->can('cancel', $withdrawal) ?? false,
                'reverse' => request()->user()?->can('reverse', $withdrawal) ?? false,
            ],
        ]);
    }

    public function update(WithdrawalRequest $request, PartnerWithdrawal $withdrawal, WithdrawalService $withdrawals): RedirectResponse
    {
        $withdrawals->update($withdrawal, $request->user(), $request->validated());

        return redirect()->route('withdrawals.show', $withdrawal)->with('success', 'Withdrawal updated.');
    }

    public function cancel(PartnerWithdrawal $withdrawal, WithdrawalService $withdrawals): RedirectResponse
    {
        $this->authorize('cancel', $withdrawal);
        $withdrawals->cancel($withdrawal, request()->user());

        return redirect()->route('withdrawals.show', $withdrawal)->with('success', 'Withdrawal cancelled.');
    }

    public function reverse(Request $request, PartnerWithdrawal $withdrawal, WithdrawalService $withdrawals): RedirectResponse
    {
        $this->authorize('reverse', $withdrawal);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        $withdrawals->reverse($withdrawal, $request->user(), $data['reason']);

        return redirect()->route('withdrawals.show', $withdrawal)->with('success', 'Withdrawal reversed.');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function paymentMethods(): array
    {
        return array_map(
            fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => str($method->value)->headline()->toString()],
            PaymentMethod::cases(),
        );
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

    /**
     * @return list<array{id: int, name: string, type: string}>
     */
    private function accounts(): array
    {
        return FinancialAccount::query()->where('is_active', true)->orderBy('name')->get()
            ->map(fn (FinancialAccount $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type->value,
            ])->all();
    }
}
