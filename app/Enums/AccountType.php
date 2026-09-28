<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Chart-of-accounts classification for the future double-entry ledger.
 */
enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Income = 'income';
    case Expense = 'expense';
}
