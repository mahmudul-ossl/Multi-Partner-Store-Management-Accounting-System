<?php

declare(strict_types=1);

namespace App\Services\Approvals;

use App\Enums\ApprovalRequestType;
use App\Exceptions\ApprovalStateException;
use App\Models\ApprovalThreshold;
use App\Support\Money;

final class ApprovalThresholdResolver
{
    public function requiredFor(ApprovalRequestType $type, string|int $amount): int
    {
        $amount = Money::of($amount)->amount();

        $rule = ApprovalThreshold::query()
            ->where('request_type', $type)
            ->where('min_amount', '<=', $amount)
            ->where(function ($query) use ($amount): void {
                $query->whereNull('max_amount')->orWhere('max_amount', '>=', $amount);
            })
            ->orderByDesc('min_amount')
            ->first();

        if (! $rule instanceof ApprovalThreshold) {
            throw new ApprovalStateException('No approval threshold is configured for this amount.');
        }

        return (int) $rule->required_approvals;
    }
}
