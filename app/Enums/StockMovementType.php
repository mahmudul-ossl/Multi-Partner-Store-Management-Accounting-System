<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ledger types for stock_movements. On-hand quantity is the sum of signed
 * quantities. InventoryService is the only writer.
 */
enum StockMovementType: string
{
    case Opening = 'opening';
    case Purchase = 'purchase';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case PurchaseReturn = 'purchase_return';
    case Damage = 'damage';
    case Adjustment = 'adjustment';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening',
            self::Purchase => 'Purchase',
            self::Sale => 'Sale',
            self::SaleReturn => 'Sale return',
            self::PurchaseReturn => 'Purchase return',
            self::Damage => 'Damage',
            self::Adjustment => 'Adjustment',
            self::TransferIn => 'Transfer in',
            self::TransferOut => 'Transfer out',
        };
    }

    public function increasesStock(): bool
    {
        return match ($this) {
            self::Opening, self::Purchase, self::SaleReturn, self::TransferIn => true,
            self::Sale, self::PurchaseReturn, self::Damage, self::TransferOut, self::Adjustment => false,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
