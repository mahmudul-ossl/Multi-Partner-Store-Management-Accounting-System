<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Http\Requests\Spending\ExpenseRequest;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Partner;
use App\Services\Spending\ExpenseService;
use App\Support\Format;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Expense::class);
        $expenses = Expense::query()->with('partner', 'approvalRequest', 'financialAccount')->latest('transaction_date')->paginate(20);

        return Inertia::render('Expenses/Index', [
            'expenses' => [
                'data' => $expenses->getCollection()->map(fn (Expense $expense): array => [
                    'id' => $expense->id,
                    'reference' => $expense->reference,
                    'date' => Format::date($expense->transaction_date),
                    'category' => $expense->category->label(),
                    'partner' => $expense->partner?->name ?? 'Business',
                    'amount' => Money::of((string) $expense->amount)->formatted(),
                    'account' => $expense->financialAccount?->name,
                    'status' => ['label' => $expense->status->label(), 'tone' => $expense->status->tone()],
                    'approval' => $expense->approvalRequest ? $expense->approvalRequest->completed_approvals.'/'.$expense->approvalRequest->required_approvals : null,
                ])->all(),
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'from' => $expenses->firstItem(),
                'to' => $expenses->lastItem(),
                'total' => $expenses->total(),
                'per_page' => $expenses->perPage(),
            ],
            'categories' => ExpenseCategory::options(),
            'partners' => Partner::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'accounts' => FinancialAccount::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'methods' => array_map(
                fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => str($method->value)->headline()->toString()],
                PaymentMethod::cases(),
            ),
            'can' => ['create' => request()->user()?->can('create', Expense::class) ?? false],
        ]);
    }

    public function store(ExpenseRequest $request, ExpenseService $expenses): RedirectResponse
    {
        $expense = $expenses->create($request->user(), $request->validated());

        return redirect()->route('expenses.show', $expense)->with('success', 'Expense submitted for approval.');
    }

    public function show(Expense $expense): Response
    {
        $this->authorize('view', $expense);
        $expense->load('partner', 'financialAccount', 'approvalRequest');

        return Inertia::render('Expenses/Show', [
            'expense' => [
                'id' => $expense->id,
                'reference' => $expense->reference,
                'date' => Format::date($expense->transaction_date),
                'category' => $expense->category->label(),
                'amount' => Money::of((string) $expense->amount)->formatted(),
                'partner' => $expense->partner?->name,
                'account' => $expense->financialAccount?->name,
                'description' => $expense->description,
                'status' => ['label' => $expense->status->label(), 'tone' => $expense->status->tone()],
                'approval' => $expense->approvalRequest ? [
                    'id' => $expense->approvalRequest->id,
                    'completed' => $expense->approvalRequest->completed_approvals,
                    'required' => $expense->approvalRequest->required_approvals,
                ] : null,
                'journal_entry_id' => $expense->journal_entry_id,
            ],
        ]);
    }
}
