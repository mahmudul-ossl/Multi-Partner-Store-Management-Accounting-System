<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\SalesIncomeSource;
use App\Http\Requests\Sales\SalesIncomeRequest;
use App\Models\FinancialAccount;
use App\Models\SalesIncome;
use App\Services\Sales\SalesIncomeService;
use App\Support\Format;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SalesIncomeController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', SalesIncome::class);
        $incomes = SalesIncome::query()->with('approvalRequest', 'financialAccount')->latest('transaction_date')->paginate(20);

        return Inertia::render('Sales/Index', [
            'incomes' => [
                'data' => $incomes->getCollection()->map(fn (SalesIncome $income): array => [
                    'id' => $income->id,
                    'reference' => $income->reference,
                    'date' => Format::date($income->transaction_date),
                    'source' => $income->source->label(),
                    'amount' => Money::of((string) $income->amount)->formatted(),
                    'account' => $income->financialAccount?->name,
                    'status' => ['label' => $income->status->label(), 'tone' => $income->status->tone()],
                    'approval' => $income->approvalRequest ? $income->approvalRequest->completed_approvals.'/'.$income->approvalRequest->required_approvals : null,
                ])->all(),
                'current_page' => $incomes->currentPage(),
                'last_page' => $incomes->lastPage(),
                'from' => $incomes->firstItem(),
                'to' => $incomes->lastItem(),
                'total' => $incomes->total(),
                'per_page' => $incomes->perPage(),
            ],
            'sources' => SalesIncomeSource::options(),
            'accounts' => FinancialAccount::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'methods' => array_map(
                fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => str($method->value)->headline()->toString()],
                PaymentMethod::cases(),
            ),
            'can' => ['create' => request()->user()?->can('create', SalesIncome::class) ?? false],
        ]);
    }

    public function store(SalesIncomeRequest $request, SalesIncomeService $incomes): RedirectResponse
    {
        $income = $incomes->create($request->user(), $request->validated());

        return redirect()->route('sales.show', $income)->with('success', 'Sale recorded.');
    }

    public function show(SalesIncome $salesIncome): Response
    {
        $this->authorize('view', $salesIncome);
        $salesIncome->load('financialAccount', 'approvalRequest');

        return Inertia::render('Sales/Show', [
            'income' => [
                'id' => $salesIncome->id,
                'reference' => $salesIncome->reference,
                'date' => Format::date($salesIncome->transaction_date),
                'source' => $salesIncome->source->label(),
                'amount' => Money::of((string) $salesIncome->amount)->formatted(),
                'account' => $salesIncome->financialAccount?->name,
                'note' => $salesIncome->note,
                'status' => ['label' => $salesIncome->status->label(), 'tone' => $salesIncome->status->tone()],
                'approval' => $salesIncome->approvalRequest ? [
                    'id' => $salesIncome->approvalRequest->id,
                    'completed' => $salesIncome->approvalRequest->completed_approvals,
                    'required' => $salesIncome->approvalRequest->required_approvals,
                ] : null,
                'journal_entry_id' => $salesIncome->journal_entry_id,
            ],
        ]);
    }
}
