<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Default chart of accounts. Phase 3 may add accounts; these codes stay.
 */
final class ChartAccountCode
{
    public const Cash = '1000';

    public const Bank = '1010';

    public const MobileWallet = '1020';

    public const Inventory = '1100';

    public const AccountsReceivable = '1200';

    public const AccountsPayable = '2000';

    public const OtherPayables = '2100';

    public const PartnerCapital = '3000';

    public const RetainedEarnings = '3100';

    public const PartnerWithdrawals = '3200';

    public const ProductSales = '4000';

    public const OtherRevenue = '4100';

    public const Cogs = '5000';

    public const PromotionExpense = '5100';

    public const Salary = '5200';

    public const Rent = '5300';

    public const Delivery = '5400';

    public const BankCharges = '5500';

    public const OtherExpenses = '5600';

    /**
     * Equity accounts that make up a partner statement.
     *
     * @var list<string>
     */
    public const PARTNER_STATEMENT = [
        self::PartnerCapital,
        self::PartnerWithdrawals,
    ];
}
