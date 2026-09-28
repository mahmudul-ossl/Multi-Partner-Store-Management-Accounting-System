<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FundingSource;
use App\Enums\PaymentMethod;
use App\Enums\PromotionPlatform;
use App\Enums\PromotionStatus;
use App\Http\Requests\Spending\PromotionContributionRequest;
use App\Http\Requests\Spending\PromotionRequest;
use App\Models\FinancialAccount;
use App\Models\Partner;
use App\Models\Promotion;
use App\Models\PromotionPartnerExpense;
use App\Services\Spending\PromotionContributionService;
use App\Services\Spending\PromotionService;
use App\Support\Format;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Promotion::class);
        $promotions = Promotion::query()->latest('starts_on')->paginate(20);

        return Inertia::render('Promotions/Index', [
            'promotions' => [
                'data' => $promotions->getCollection()->map(fn (Promotion $promotion): array => [
                    'id' => $promotion->id,
                    'name' => $promotion->name,
                    'platform' => $promotion->platform->label(),
                    'starts' => Format::date($promotion->starts_on),
                    'ends' => $promotion->ends_on ? Format::date($promotion->ends_on) : '—',
                    'budget' => Money::of((string) $promotion->budget)->formatted(),
                    'actual' => Money::of((string) $promotion->actual_amount)->formatted(),
                    'status' => ['label' => $promotion->status->label(), 'tone' => $promotion->status->tone()],
                ])->all(),
                'current_page' => $promotions->currentPage(),
                'last_page' => $promotions->lastPage(),
                'from' => $promotions->firstItem(),
                'to' => $promotions->lastItem(),
                'total' => $promotions->total(),
                'per_page' => $promotions->perPage(),
            ],
            'platforms' => PromotionPlatform::options(),
            'statuses' => PromotionStatus::options(),
            'can' => ['create' => request()->user()?->can('create', Promotion::class) ?? false],
        ]);
    }

    public function store(PromotionRequest $request, PromotionService $promotions): RedirectResponse
    {
        $promotion = $promotions->create($request->user(), $request->validated());

        return redirect()->route('promotions.show', $promotion)->with('success', 'Promotion saved.');
    }

    public function show(Promotion $promotion): Response
    {
        $this->authorize('view', $promotion);
        $promotion->load('contributions.partner', 'contributions.approvalRequest', 'contributions.financialAccount');

        return Inertia::render('Promotions/Show', [
            'promotion' => [
                'id' => $promotion->id,
                'name' => $promotion->name,
                'platform' => $promotion->platform->label(),
                'starts' => Format::date($promotion->starts_on),
                'ends' => $promotion->ends_on ? Format::date($promotion->ends_on) : '—',
                'budget' => Money::of((string) $promotion->budget)->formatted(),
                'actual' => Money::of((string) $promotion->actual_amount)->formatted(),
                'description' => $promotion->description,
                'status' => ['label' => $promotion->status->label(), 'tone' => $promotion->status->tone(), 'value' => $promotion->status->value],
                'open' => $promotion->status->acceptsContributions(),
                'contributions' => $promotion->contributions->map(fn (PromotionPartnerExpense $row): array => [
                    'id' => $row->id,
                    'reference' => $row->reference,
                    'date' => Format::date($row->transaction_date),
                    'partner' => $row->partner?->name,
                    'funded_by' => $row->funded_by->label(),
                    'amount' => Money::of((string) $row->amount)->formatted(),
                    'account' => $row->financialAccount?->name,
                    'note' => $row->note,
                    'status' => ['label' => $row->status->label(), 'tone' => $row->status->tone()],
                    'approval' => $row->approvalRequest ? $row->approvalRequest->completed_approvals.'/'.$row->approvalRequest->required_approvals : null,
                    'approval_id' => $row->approvalRequest?->id,
                    'journal_entry_id' => $row->journal_entry_id,
                ])->all(),
            ],
            'partners' => Partner::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'accounts' => FinancialAccount::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'methods' => $this->methods(),
            'funding' => FundingSource::options(),
            'can' => ['contribute' => request()->user()?->can('create', PromotionPartnerExpense::class) ?? false],
        ]);
    }

    public function storeContribution(PromotionContributionRequest $request, Promotion $promotion, PromotionContributionService $contributions): RedirectResponse
    {
        $contributions->create($request->user(), $promotion, $request->validated());

        return redirect()->route('promotions.show', $promotion)->with('success', 'Contribution submitted for approval.');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function methods(): array
    {
        return array_map(
            fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => str($method->value)->headline()->toString()],
            PaymentMethod::cases(),
        );
    }
}
