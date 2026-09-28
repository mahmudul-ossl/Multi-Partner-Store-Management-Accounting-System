<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\Finance\InvestmentRequest;
use App\Http\Resources\InvestmentResource;
use App\Models\FinancialAccount;
use App\Models\Partner;
use App\Models\PartnerInvestment;
use App\Models\User;
use App\Services\Finance\FinanceDirectory;
use App\Services\Finance\FinancePresenter;
use App\Services\Finance\InvestmentService;
use App\Services\PartnerDirectory;
use App\Support\PaginatorPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvestmentController extends Controller
{
    public function index(Request $request, FinanceDirectory $directory, PartnerDirectory $partners): Response
    {
        $this->authorize('viewAny', PartnerInvestment::class);
        $filters = $request->only(['search', 'status', 'sort', 'direction']);

        return Inertia::render('Investments/Index', [
            'investments' => PaginatorPayload::make(
                $directory->investments($request->user(), $filters),
                InvestmentResource::class,
            ),
            'filters' => $this->filters($filters),
            'statuses' => DocumentStatus::options(),
            'paymentMethods' => $this->paymentMethods(),
            'partners' => $this->partnerOptions($partners, $request->user()),
            'accounts' => $this->accounts(),
        ]);
    }

    public function store(InvestmentRequest $request, InvestmentService $investments): RedirectResponse
    {
        $investment = $investments->create($request->user(), $request->validated());

        return redirect()->route('investments.show', $investment)->with('success', 'Investment submitted for approval.');
    }

    public function show(PartnerInvestment $investment, FinancePresenter $presenter): Response
    {
        $this->authorize('view', $investment);

        return Inertia::render('Investments/Show', [
            'investment' => $presenter->investment($investment),
            'paymentMethods' => $this->paymentMethods(),
            'partners' => $this->partnerOptions(app(PartnerDirectory::class), request()->user()),
            'accounts' => $this->accounts(),
            'can' => [
                'update' => request()->user()?->can('update', $investment) ?? false,
                'cancel' => request()->user()?->can('cancel', $investment) ?? false,
                'reverse' => request()->user()?->can('reverse', $investment) ?? false,
            ],
        ]);
    }

    public function update(InvestmentRequest $request, PartnerInvestment $investment, InvestmentService $investments): RedirectResponse
    {
        $investments->update($investment, $request->user(), $request->validated());

        return redirect()->route('investments.show', $investment)->with('success', 'Investment updated.');
    }

    public function cancel(PartnerInvestment $investment, InvestmentService $investments): RedirectResponse
    {
        $this->authorize('cancel', $investment);
        $investments->cancel($investment, request()->user());

        return redirect()->route('investments.show', $investment)->with('success', 'Investment cancelled.');
    }

    public function reverse(Request $request, PartnerInvestment $investment, InvestmentService $investments): RedirectResponse
    {
        $this->authorize('reverse', $investment);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        $investments->reverse($investment, $request->user(), $data['reason']);

        return redirect()->route('investments.show', $investment)->with('success', 'Investment reversed.');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    private function filters(array $filters): array
    {
        return [
            'search' => (string) ($filters['search'] ?? ''),
            'status' => (string) ($filters['status'] ?? ''),
            'sort' => (string) ($filters['sort'] ?? 'transaction_date'),
            'direction' => (string) ($filters['direction'] ?? 'desc'),
        ];
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
