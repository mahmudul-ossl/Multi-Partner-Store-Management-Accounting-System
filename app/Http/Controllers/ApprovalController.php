<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ApprovalRequestType;
use App\Http\Requests\Approvals\ApproveRequest;
use App\Http\Requests\Approvals\RejectRequest;
use App\Http\Resources\ApprovalResource;
use App\Models\ApprovalRequest;
use App\Services\Approvals\ApprovalDirectory;
use App\Services\Approvals\ApprovalService;
use App\Services\Finance\FinancePresenter;
use App\Support\PaginatorPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function index(Request $request, ApprovalDirectory $directory): Response
    {
        $this->authorize('viewAny', ApprovalRequest::class);
        $filters = $request->only(['search', 'type', 'sort', 'direction']);

        return Inertia::render('Approvals/Index', [
            'approvals' => PaginatorPayload::make($directory->actionable($request->user(), $filters), ApprovalResource::class),
            'filters' => [
                'search' => (string) ($filters['search'] ?? ''),
                'type' => (string) ($filters['type'] ?? ''),
                'sort' => (string) ($filters['sort'] ?? 'requested_at'),
                'direction' => (string) ($filters['direction'] ?? 'desc'),
            ],
            'types' => ApprovalRequestType::options(),
        ]);
    }

    public function show(ApprovalRequest $approval, FinancePresenter $presenter): Response
    {
        $this->authorize('view', $approval);

        return Inertia::render('Approvals/Show', [
            'approval' => $presenter->approval($approval, request()->user()),
        ]);
    }

    public function approve(ApproveRequest $request, ApprovalRequest $approval, ApprovalService $approvals): RedirectResponse
    {
        $approvals->approve($approval, $request->user(), $request->validated('comment'));

        return redirect()->route('approvals.show', $approval)->with('success', 'Approval recorded.');
    }

    public function reject(RejectRequest $request, ApprovalRequest $approval, ApprovalService $approvals): RedirectResponse
    {
        $approvals->reject($approval, $request->user(), $request->validated('comment'));

        return redirect()->route('approvals.show', $approval)->with('success', 'Request rejected. No journal entry was posted.');
    }
}
