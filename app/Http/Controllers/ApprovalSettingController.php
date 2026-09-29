<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ApprovalRequestType;
use App\Http\Requests\Approvals\ThresholdRequest;
use App\Models\ApprovalThreshold;
use App\Services\Approvals\ApprovalThresholdService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalSettingController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', ApprovalThreshold::class);

        $thresholds = ApprovalThreshold::query()
            ->orderBy('request_type')
            ->orderBy('min_amount')
            ->get()
            ->map(fn (ApprovalThreshold $threshold): array => [
                'id' => $threshold->id,
                'request_type' => $threshold->request_type->value,
                'request_type_label' => $threshold->request_type->label(),
                'min_amount' => (string) $threshold->min_amount,
                'max_amount' => $threshold->max_amount === null ? null : (string) $threshold->max_amount,
                'min_formatted' => Money::of((string) $threshold->min_amount)->formatted(),
                'max_formatted' => $threshold->max_amount === null ? 'No maximum' : Money::of((string) $threshold->max_amount)->formatted(),
                'required_approvals' => $threshold->required_approvals,
            ])->all();

        return Inertia::render('Settings/Approvals', [
            'thresholds' => $thresholds,
            'types' => ApprovalRequestType::options(),
        ]);
    }

    public function store(ThresholdRequest $request, ApprovalThresholdService $thresholds): RedirectResponse
    {
        $thresholds->save($request->validated());

        return redirect()->route('settings.approvals.index')->with('success', 'Approval band saved.');
    }

    public function update(ThresholdRequest $request, ApprovalThreshold $threshold, ApprovalThresholdService $thresholds): RedirectResponse
    {
        $thresholds->save($request->validated(), $threshold);

        return redirect()->route('settings.approvals.index')->with('success', 'Approval band updated.');
    }

    public function destroy(ApprovalThreshold $threshold, ApprovalThresholdService $thresholds): RedirectResponse
    {
        $this->authorize('delete', $threshold);
        $thresholds->delete($threshold);

        return redirect()->route('settings.approvals.index')->with('success', 'Approval band removed.');
    }
}
