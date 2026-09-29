<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\ApprovalRequestType;
use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Models\Sale;
use App\Models\SalesCancellation;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\AuditLogService;
use App\Support\Sequence;
use Illuminate\Support\Facades\DB;

final class SalesCancellationService
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly AuditLogService $audit,
        private readonly SaleRules $rules,
    ) {}

    /**
     * @param  array{sale_id: int, transaction_date: string, reason: string}  $attributes
     */
    public function create(User $actor, array $attributes): SalesCancellation
    {
        return DB::transaction(function () use ($actor, $attributes): SalesCancellation {
            $sale = Sale::query()->lockForUpdate()->findOrFail($attributes['sale_id']);
            $this->rules->assertCancellable($sale);

            $cancellation = SalesCancellation::query()->create([
                'reference' => Sequence::next(SalesCancellation::class, 'reference', 'SCN'),
                'sale_id' => $sale->id,
                'transaction_date' => $attributes['transaction_date'],
                'reason' => $attributes['reason'],
                'status' => DocumentStatus::Pending,
                'created_by' => $actor->id,
            ]);

            $this->approvals->submit($cancellation, ApprovalRequestType::SalesCancellation, (string) $sale->total, $actor, $cancellation->reason);
            $this->audit->record(AuditAction::Created, $cancellation, null, [
                'reference' => $cancellation->reference,
                'sale' => $sale->reference,
            ], $actor);

            return $cancellation->load('approvalRequest');
        });
    }
}
