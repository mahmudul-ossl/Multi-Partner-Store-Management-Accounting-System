<?php

declare(strict_types=1);

namespace App\Services\Approvals;

use App\Enums\ApprovalActionType;
use App\Enums\ApprovalRequestType;
use App\Enums\ApprovalStatus;
use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Exceptions\ApprovalStateException;
use App\Exceptions\DuplicateApprovalException;
use App\Models\AccountTransfer;
use App\Models\ApprovalRequest;
use App\Models\Expense;
use App\Models\ManualJournal;
use App\Models\ProfitAllocation;
use App\Models\PromotionPartnerExpense;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\SalesCancellation;
use App\Models\StockAdjustment;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Accounting\AllocationPoster;
use App\Services\AuditLogService;
use App\Services\Inventory\InventoryPoster;
use App\Services\Ledger\AccountingPoster;
use App\Services\Ledger\PartnerFinancePoster;
use App\Services\Sales\SalesPoster;
use App\Services\Spending\SpendingPoster;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class ApprovalService
{
    public function __construct(
        private readonly ApprovalThresholdResolver $thresholds,
        private readonly ApprovalNotifier $notifier,
        private readonly PartnerFinancePoster $poster,
        private readonly AccountingPoster $accounting,
        private readonly InventoryPoster $inventory,
        private readonly SalesPoster $sales,
        private readonly SpendingPoster $spending,
        private readonly AllocationPoster $allocation,
        private readonly AuditLogService $audit,
    ) {}

    public function submit(Model $document, ApprovalRequestType $type, string|int $amount, User $requester, ?string $note = null): ApprovalRequest
    {
        return DB::transaction(function () use ($document, $type, $amount, $requester, $note): ApprovalRequest {
            $money = Money::of($amount)->amount();
            $required = $this->thresholds->requiredFor($type, $money);

            $request = ApprovalRequest::query()->create([
                'request_type' => $type,
                'reference_type' => $document->getMorphClass(),
                'reference_id' => $document->getKey(),
                'requested_by' => $requester->id,
                'status' => ApprovalStatus::Pending,
                'current_step' => 1,
                'required_approvals' => $required,
                'completed_approvals' => 0,
                'amount' => $money,
                'requested_at' => now(),
                'note' => $note,
            ]);

            $request->actions()->create([
                'user_id' => $requester->id,
                'action' => ApprovalActionType::Submitted,
                'comment' => $note,
            ]);

            $this->notifier->requested($request);

            return $request;
        });
    }

    public function approve(ApprovalRequest $approvalRequest, User $actor, ?string $comment = null): ApprovalRequest
    {
        return DB::transaction(function () use ($approvalRequest, $actor, $comment): ApprovalRequest {
            $request = $this->lockOpen($approvalRequest);
            SelfApprovalGuard::assertNotSelf((int) $request->requested_by, (int) $actor->id);
            $this->assertCanDecide($request, $actor);
            $this->assertNotAlreadyApproved($request, $actor);

            $request->actions()->create([
                'user_id' => $actor->id,
                'action' => ApprovalActionType::Approved,
                'comment' => $comment,
            ]);

            $request->completed_approvals = (int) $request->completed_approvals + 1;
            $request->current_step = min($request->completed_approvals + 1, (int) $request->required_approvals);

            $document = $this->lockDocument($request);

            if ($request->completed_approvals < $request->required_approvals) {
                $request->status = ApprovalStatus::PartiallyApproved;
                $request->save();
                $this->audit->record(AuditAction::Approved, $request, null, [
                    'completed_approvals' => $request->completed_approvals,
                    'required_approvals' => $request->required_approvals,
                    'comment' => $comment,
                ], $actor);

                return $request;
            }

            $request->status = ApprovalStatus::Approved;
            $request->approved_at = now();
            $request->current_step = (int) $request->required_approvals;
            $request->save();

            $document->status = DocumentStatus::Approved;
            $document->save();

            $this->postDocument($document, $actor);

            $this->audit->record(AuditAction::Approved, $document, null, [
                'status' => DocumentStatus::Approved->value,
                'journal_entry_id' => $document->journal_entry_id,
            ], $actor);

            $this->notifier->decided($request, 'approved');

            return $request->fresh(['actions.user', 'reference']);
        });
    }

    public function reject(ApprovalRequest $approvalRequest, User $actor, string $comment): ApprovalRequest
    {
        return DB::transaction(function () use ($approvalRequest, $actor, $comment): ApprovalRequest {
            $request = $this->lockOpen($approvalRequest);
            $this->assertCanDecide($request, $actor);

            if ((int) $request->requested_by === (int) $actor->id) {
                throw new AuthorizationException('You cannot reject your own transaction.');
            }

            $request->actions()->create([
                'user_id' => $actor->id,
                'action' => ApprovalActionType::Rejected,
                'comment' => $comment,
            ]);

            $request->status = ApprovalStatus::Rejected;
            $request->rejected_at = now();
            $request->save();

            $document = $this->lockDocument($request);
            $document->status = DocumentStatus::Rejected;
            $document->save();

            $this->audit->record(AuditAction::Rejected, $document, null, [
                'status' => DocumentStatus::Rejected->value,
                'comment' => $comment,
            ], $actor);

            $this->notifier->decided($request, 'rejected');

            return $request;
        });
    }

    public function cancel(ApprovalRequest $approvalRequest, User $actor, ?string $comment = null): ApprovalRequest
    {
        return DB::transaction(function () use ($approvalRequest, $actor, $comment): ApprovalRequest {
            $request = $this->lockOpen($approvalRequest);
            $document = $this->lockDocument($request);

            $request->actions()->create([
                'user_id' => $actor->id,
                'action' => ApprovalActionType::Cancelled,
                'comment' => $comment,
            ]);

            $request->status = ApprovalStatus::Cancelled;
            $request->save();

            $document->status = DocumentStatus::Cancelled;
            $document->save();

            $this->audit->record(AuditAction::Cancelled, $document, null, [
                'status' => DocumentStatus::Cancelled->value,
            ], $actor);

            return $request;
        });
    }

    private function postDocument(Model $document, User $actor): void
    {
        if ($document instanceof ManualJournal || $document instanceof AccountTransfer) {
            $this->accounting->post($document, $actor);

            return;
        }

        if ($document instanceof Purchase || $document instanceof PurchaseReturn || $document instanceof SupplierPayment || $document instanceof StockAdjustment) {
            $this->inventory->post($document, $actor);

            return;
        }

        if ($document instanceof Sale || $document instanceof Refund || $document instanceof SalesCancellation) {
            $this->sales->post($document, $actor);

            return;
        }

        if ($document instanceof PromotionPartnerExpense || $document instanceof Expense) {
            $this->spending->post($document, $actor);

            return;
        }

        if ($document instanceof ProfitAllocation) {
            $this->allocation->post($document, $actor);

            return;
        }

        $this->poster->post($document, $actor);
    }

    public function syncAmount(Model $document, ApprovalRequestType $type, string|int $amount): void
    {
        $money = Money::of($amount)->amount();
        $request = $document->approvalRequest()->lockForUpdate()->first();

        if (! $request instanceof ApprovalRequest) {
            throw new ApprovalStateException('This document has no approval request.');
        }

        $request->amount = $money;
        $request->required_approvals = $this->thresholds->requiredFor($type, $money);
        $request->save();
    }

    private function lockOpen(ApprovalRequest $approvalRequest): ApprovalRequest
    {
        $request = ApprovalRequest::query()->whereKey($approvalRequest->id)->lockForUpdate()->firstOrFail();

        if (! $request->status->isOpen()) {
            throw new ApprovalStateException('This request is no longer waiting for approval.');
        }

        return $request;
    }

    private function lockDocument(ApprovalRequest $request): Model
    {
        $document = $request->reference()->lockForUpdate()->first();

        if (! $document instanceof Model) {
            throw new ApprovalStateException('The request has no document.');
        }

        if ($document->status !== DocumentStatus::Pending) {
            throw new ApprovalStateException('This document is no longer pending.');
        }

        return $document;
    }

    private function assertCanDecide(ApprovalRequest $request, User $actor): void
    {
        if (! $actor->can($request->request_type->approvePermission()->value)) {
            throw new AuthorizationException('You do not have permission to decide this request.');
        }
    }

    private function assertNotAlreadyApproved(ApprovalRequest $request, User $actor): void
    {
        $already = $request->actions()
            ->where('user_id', $actor->id)
            ->where('action', ApprovalActionType::Approved)
            ->exists();

        if ($already) {
            throw new DuplicateApprovalException('You have already approved this request.');
        }
    }
}
