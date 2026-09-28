<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\ChartAccountCode;

enum ExpenseCategory: string
{
    case Rent = 'rent';
    case Salary = 'salary';
    case Electricity = 'electricity';
    case Internet = 'internet';
    case Packaging = 'packaging';
    case Delivery = 'delivery';
    case Marketing = 'marketing';
    case FacebookAds = 'facebook_ads';
    case Software = 'software';
    case Transport = 'transport';
    case BankCharges = 'bank_charges';
    case OfficeExpense = 'office_expense';
    case Misc = 'misc';

    public function label(): string
    {
        return match ($this) {
            self::Rent => 'Rent',
            self::Salary => 'Salary',
            self::Electricity => 'Electricity',
            self::Internet => 'Internet',
            self::Packaging => 'Packaging',
            self::Delivery => 'Delivery',
            self::Marketing => 'Marketing',
            self::FacebookAds => 'Facebook Ads',
            self::Software => 'Software',
            self::Transport => 'Transport',
            self::BankCharges => 'Bank Charges',
            self::OfficeExpense => 'Office Expense',
            self::Misc => 'Misc',
        };
    }

    public function accountCode(): string
    {
        return match ($this) {
            self::Rent => ChartAccountCode::Rent,
            self::Salary => ChartAccountCode::Salary,
            self::Electricity => ChartAccountCode::Electricity,
            self::Internet => ChartAccountCode::Internet,
            self::Packaging => ChartAccountCode::Packaging,
            self::Delivery => ChartAccountCode::Delivery,
            self::Marketing => ChartAccountCode::Marketing,
            self::FacebookAds => ChartAccountCode::FacebookAds,
            self::Software => ChartAccountCode::Software,
            self::Transport => ChartAccountCode::Transport,
            self::BankCharges => ChartAccountCode::BankCharges,
            self::OfficeExpense => ChartAccountCode::OfficeExpense,
            self::Misc => ChartAccountCode::OtherExpenses,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $category): array => ['value' => $category->value, 'label' => $category->label()],
            self::cases(),
        );
    }
}
