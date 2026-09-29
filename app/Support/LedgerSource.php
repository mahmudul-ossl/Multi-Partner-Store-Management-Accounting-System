<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Morph class names later phases must use so partner dashboards can split
 * capital credits without subtracting investment, withdrawal, and transfer
 * totals by hand. The classes do not exist until those phases.
 */
final class LedgerSource
{
    public const PromotionContribution = 'App\\Models\\PromotionPartnerExpense';

    public const PartnerExpense = 'App\\Models\\Expense';

    public const ProfitShare = 'App\\Models\\ProfitAllocation';
}
