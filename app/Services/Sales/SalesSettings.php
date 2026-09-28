<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\SalesSetting;
use App\Support\Money;

/**
 * Discounts above this amount wait for large-discount approval.
 * The number of approvers still comes from the approval bands.
 */
final class SalesSettings
{
    public function threshold(): string
    {
        $stored = SalesSetting::query()->value('large_discount_threshold');

        if ($stored === null) {
            return Money::of('1000.00')->amount();
        }

        return Money::of((string) $stored)->amount();
    }

    public function needsApproval(string|int $discount): bool
    {
        return Money::of($discount)->compare($this->threshold()) === 1;
    }

    public function updateThreshold(string|int $amount): SalesSetting
    {
        $setting = SalesSetting::query()->first() ?? new SalesSetting;
        $setting->large_discount_threshold = Money::of($amount)->amount();
        $setting->save();

        return $setting;
    }
}
