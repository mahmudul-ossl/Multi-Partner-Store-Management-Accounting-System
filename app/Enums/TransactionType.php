<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Business events that will post to the double-entry ledger in a later phase.
 */
enum TransactionType: string
{
    case Investment = 'investment';
    case Withdrawal = 'withdrawal';
    case Promotion = 'promotion';
    case Expense = 'expense';
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Refund = 'refund';
    case Transfer = 'transfer';
    case Adjustment = 'adjustment';
    case ProfitShare = 'profit_share';
    case OpeningBalance = 'opening_balance';
}
