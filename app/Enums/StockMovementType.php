<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ledger types for the future stock_movements table. Inventory on-hand
 * will be derived from this ledger, not stored as a mutable balance alone.
 */
enum StockMovementType: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Adjustment = 'adjustment';
    case Return = 'return';
    case Transfer = 'transfer';
    case Opening = 'opening';
}
