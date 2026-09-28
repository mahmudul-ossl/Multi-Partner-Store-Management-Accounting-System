<?php

declare(strict_types=1);

namespace App\Services\Spending;

use App\Enums\AuditAction;
use App\Enums\PromotionPlatform;
use App\Enums\PromotionStatus;
use App\Exceptions\ApprovalStateException;
use App\Models\Promotion;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\Money;

final class PromotionService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @param  array{name: string, platform: string, starts_on: string, ends_on?: string|null, budget: string|int, status: string, description?: string|null}  $attributes
     */
    public function create(User $actor, array $attributes): Promotion
    {
        $ends = $attributes['ends_on'] ?? null;

        if (is_string($ends) && $ends !== '' && $ends < $attributes['starts_on']) {
            throw new ApprovalStateException('The end date cannot be before the start date.');
        }

        $budget = Money::of($attributes['budget'])->amount();

        if (Money::of($budget)->compare('0.00') !== 1) {
            throw new ApprovalStateException('The budget must be greater than zero.');
        }

        $promotion = Promotion::query()->create([
            'name' => $attributes['name'],
            'platform' => PromotionPlatform::from($attributes['platform']),
            'starts_on' => $attributes['starts_on'],
            'ends_on' => $ends === '' ? null : $ends,
            'budget' => $budget,
            'actual_amount' => '0.00',
            'status' => PromotionStatus::from($attributes['status']),
            'description' => $attributes['description'] ?? null,
            'created_by' => $actor->id,
        ]);

        $this->audit->record(AuditAction::Created, $promotion, null, [
            'name' => $promotion->name,
            'budget' => $budget,
        ], $actor);

        return $promotion;
    }
}
