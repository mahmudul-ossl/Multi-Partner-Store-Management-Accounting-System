<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\AllocationMethod;
use App\Enums\ApprovalRequestType;
use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Enums\PartnerStatus;
use App\Exceptions\ApprovalStateException;
use App\Models\Partner;
use App\Models\ProfitAllocation;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\AuditLogService;
use App\Support\AllocationSplit;
use App\Support\Money;
use App\Support\Sequence;
use Illuminate\Support\Facades\DB;

/**
 * Stores the partner shares at creation time, then waits for approval.
 * Ownership and investment weights are the active partners' stored
 * percentages. Custom percentages must add up to exactly 100%.
 */
final class AllocationService
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly PeriodGuard $periods,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $actor, array $attributes): ProfitAllocation
    {
        return DB::transaction(function () use ($actor, $attributes): ProfitAllocation {
            $amount = Money::of($attributes['amount'])->amount();

            if (Money::of($amount)->compare('0.00') !== 1) {
                throw new ApprovalStateException('The allocation amount must be greater than zero.');
            }

            $date = substr((string) $attributes['transaction_date'], 0, 10);
            $this->periods->assertOpen($date);
            $method = AllocationMethod::from((string) $attributes['method']);
            $weights = $this->weights($method, $attributes['lines'] ?? []);
            $shares = AllocationSplit::apply($amount, $weights);

            $allocation = ProfitAllocation::query()->create([
                'reference' => Sequence::next(ProfitAllocation::class, 'reference', 'PAL'),
                'method' => $method,
                'amount' => $amount,
                'transaction_date' => $date,
                'note' => $attributes['note'] ?? null,
                'status' => DocumentStatus::Pending,
                'created_by' => $actor->id,
            ]);

            foreach ($shares as $share) {
                $allocation->lines()->create($share);
            }

            $this->approvals->submit(
                $allocation,
                ApprovalRequestType::ProfitAllocation,
                $amount,
                $actor,
                $attributes['note'] ?? null,
            );

            $this->audit->record(AuditAction::Created, $allocation, null, [
                'reference' => $allocation->reference,
                'method' => $method->value,
                'amount' => $amount,
                'status' => DocumentStatus::Pending->value,
            ], $actor);

            return $allocation->load('lines');
        });
    }

    /**
     * @return list<array{partner_id: int, percentage: string}>
     */
    private function weights(AllocationMethod $method, mixed $lines): array
    {
        if ($method === AllocationMethod::Custom) {
            return $this->customWeights(is_array($lines) ? $lines : []);
        }

        $column = $method === AllocationMethod::Ownership ? 'ownership_percentage' : 'investment_percentage';

        return Partner::query()
            ->where('status', PartnerStatus::Active)
            ->orderBy('id')
            ->get()
            ->filter(fn (Partner $partner): bool => bccomp((string) $partner->{$column}, '0.0000', 4) === 1)
            ->map(fn (Partner $partner): array => [
                'partner_id' => (int) $partner->id,
                'percentage' => bcadd((string) $partner->{$column}, '0', 4),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $lines
     * @return list<array{partner_id: int, percentage: string}>
     */
    private function customWeights(array $lines): array
    {
        if ($lines === []) {
            throw new ApprovalStateException('A custom allocation needs a percentage for each included partner.');
        }

        $weights = [];
        $seen = [];
        $total = '0.0000';

        foreach ($lines as $line) {
            if (! is_array($line)) {
                throw new ApprovalStateException('A custom allocation needs a percentage for each included partner.');
            }

            $partnerId = (int) ($line['partner_id'] ?? 0);
            $percentage = $this->percentage((string) ($line['percentage'] ?? ''));

            if (isset($seen[$partnerId])) {
                throw new ApprovalStateException('Each partner can appear only once on a custom allocation.');
            }

            if (bccomp($percentage, '0.0000', 4) !== 1) {
                throw new ApprovalStateException('Each custom percentage must be greater than zero.');
            }

            $seen[$partnerId] = true;
            $total = bcadd($total, $percentage, 4);
            $weights[] = [
                'partner_id' => $partnerId,
                'percentage' => $percentage,
            ];
        }

        if (bccomp($total, '100.0000', 4) !== 0) {
            throw new ApprovalStateException('Custom percentages must total 100%.');
        }

        return $weights;
    }

    private function percentage(string $value): string
    {
        if (! preg_match('/^\d+(\.\d{1,4})?$/', $value)) {
            throw new ApprovalStateException('Each custom percentage must be greater than zero.');
        }

        return bcadd($value, '0', 4);
    }
}
