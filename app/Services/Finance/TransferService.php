<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\ApprovalRequestType;
use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Exceptions\ImmutableDocumentException;
use App\Models\PartnerTransfer;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\AuditLogService;
use App\Services\Ledger\PartnerFinancePoster;
use App\Support\Money;
use App\Support\Sequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransferService
{
    public function __construct(
        private readonly PartnerFinanceGuard $guard,
        private readonly ApprovalService $approvals,
        private readonly PartnerFinancePoster $poster,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array{from_partner_id: int, to_partner_id: int, amount: string|int, transaction_date: string, note?: string|null}  $attributes
     */
    public function create(User $actor, array $attributes): PartnerTransfer
    {
        return DB::transaction(function () use ($actor, $attributes): PartnerTransfer {
            $this->assertPartners($attributes);
            $this->guard->assertOwnPartner($actor, (int) $attributes['from_partner_id']);

            $transfer = PartnerTransfer::query()->create([
                'from_partner_id' => $attributes['from_partner_id'],
                'to_partner_id' => $attributes['to_partner_id'],
                'amount' => Money::of($attributes['amount'])->amount(),
                'transaction_date' => $attributes['transaction_date'],
                'reference' => Sequence::next(PartnerTransfer::class, 'reference', 'TRF'),
                'note' => $attributes['note'] ?? null,
                'status' => DocumentStatus::Pending,
                'created_by' => $actor->id,
            ]);

            $this->approvals->submit(
                $transfer,
                ApprovalRequestType::PartnerTransfer,
                (string) $transfer->amount,
                $actor,
                $transfer->note,
            );

            $this->audit->record(AuditAction::Created, $transfer, null, $this->snapshot($transfer), $actor);

            return $transfer->load('approvalRequest');
        });
    }

    /**
     * @param  array{from_partner_id: int, to_partner_id: int, amount: string|int, transaction_date: string, note?: string|null}  $attributes
     */
    public function update(PartnerTransfer $transfer, User $actor, array $attributes): PartnerTransfer
    {
        return DB::transaction(function () use ($transfer, $actor, $attributes): PartnerTransfer {
            $transfer = PartnerTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if (! $transfer->isEditable()) {
                throw new ImmutableDocumentException('Approved records cannot be edited.');
            }

            $this->assertPartners($attributes);
            $this->guard->assertOwnPartner($actor, (int) $attributes['from_partner_id']);

            $before = $this->snapshot($transfer);
            $transfer->fill([
                'from_partner_id' => $attributes['from_partner_id'],
                'to_partner_id' => $attributes['to_partner_id'],
                'amount' => Money::of($attributes['amount'])->amount(),
                'transaction_date' => $attributes['transaction_date'],
                'note' => $attributes['note'] ?? null,
            ]);
            $transfer->save();

            $this->approvals->syncAmount($transfer, ApprovalRequestType::PartnerTransfer, (string) $transfer->amount);
            $this->audit->record(AuditAction::Updated, $transfer, $before, $this->snapshot($transfer), $actor);

            return $transfer;
        });
    }

    public function cancel(PartnerTransfer $transfer, User $actor, ?string $comment = null): PartnerTransfer
    {
        $request = $transfer->approvalRequest()->firstOrFail();
        $this->approvals->cancel($request, $actor, $comment);

        return $transfer->fresh();
    }

    public function reverse(PartnerTransfer $transfer, User $actor, string $reason): PartnerTransfer
    {
        return DB::transaction(function () use ($transfer, $actor, $reason): PartnerTransfer {
            $transfer = PartnerTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if ($transfer->status !== DocumentStatus::Approved) {
                throw new ImmutableDocumentException('Only an approved transfer can be reversed.');
            }

            $this->poster->reverse($transfer, $actor, $reason);
            $transfer->status = DocumentStatus::Reversed;
            $transfer->save();

            $this->audit->record(AuditAction::Reversed, $transfer, null, [
                'status' => DocumentStatus::Reversed->value,
                'reason' => $reason,
            ], $actor);

            return $transfer;
        });
    }

    /**
     * @param  array{from_partner_id: int, to_partner_id: int}  $attributes
     */
    private function assertPartners(array $attributes): void
    {
        if ((int) $attributes['from_partner_id'] === (int) $attributes['to_partner_id']) {
            throw ValidationException::withMessages([
                'to_partner_id' => 'Choose a different receiving partner.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(PartnerTransfer $transfer): array
    {
        return [
            'reference' => $transfer->reference,
            'from_partner_id' => $transfer->from_partner_id,
            'to_partner_id' => $transfer->to_partner_id,
            'amount' => (string) $transfer->amount,
            'transaction_date' => $transfer->transaction_date?->toDateString(),
            'status' => $transfer->status->value,
            'note' => $transfer->note,
        ];
    }
}
