<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\NormalBalance;
use App\Models\ChartOfAccount;
use App\Support\ChartAccountCode;
use Illuminate\Database\Seeder;

class ChartOfAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [ChartAccountCode::Cash, 'Cash', AccountType::Asset, NormalBalance::Debit],
            [ChartAccountCode::Bank, 'Bank', AccountType::Asset, NormalBalance::Debit],
            [ChartAccountCode::MobileWallet, 'Mobile Wallet', AccountType::Asset, NormalBalance::Debit],
            [ChartAccountCode::Inventory, 'Inventory', AccountType::Asset, NormalBalance::Debit],
            [ChartAccountCode::AccountsReceivable, 'Accounts Receivable', AccountType::Asset, NormalBalance::Debit],
            [ChartAccountCode::AccountsPayable, 'Accounts Payable', AccountType::Liability, NormalBalance::Credit],
            [ChartAccountCode::OtherPayables, 'Other Payables', AccountType::Liability, NormalBalance::Credit],
            [ChartAccountCode::PartnerCapital, 'Partner Capital', AccountType::Equity, NormalBalance::Credit],
            [ChartAccountCode::RetainedEarnings, 'Retained Earnings', AccountType::Equity, NormalBalance::Credit],
            [ChartAccountCode::PartnerWithdrawals, 'Partner Withdrawals', AccountType::Equity, NormalBalance::Debit],
            [ChartAccountCode::ProductSales, 'Product Sales', AccountType::Income, NormalBalance::Credit],
            [ChartAccountCode::OtherRevenue, 'Other Revenue', AccountType::Income, NormalBalance::Credit],
            [ChartAccountCode::Cogs, 'COGS', AccountType::Expense, NormalBalance::Debit],
            [ChartAccountCode::PromotionExpense, 'Promotion Expense', AccountType::Expense, NormalBalance::Debit],
            [ChartAccountCode::Salary, 'Salary', AccountType::Expense, NormalBalance::Debit],
            [ChartAccountCode::Rent, 'Rent', AccountType::Expense, NormalBalance::Debit],
            [ChartAccountCode::Delivery, 'Delivery', AccountType::Expense, NormalBalance::Debit],
            [ChartAccountCode::BankCharges, 'Bank Charges', AccountType::Expense, NormalBalance::Debit],
            [ChartAccountCode::OtherExpenses, 'Other Expenses', AccountType::Expense, NormalBalance::Debit],
        ];

        foreach ($accounts as [$code, $name, $type, $normal]) {
            ChartOfAccount::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'normal_balance' => $normal,
                    'is_active' => true,
                ],
            );
        }
    }
}
