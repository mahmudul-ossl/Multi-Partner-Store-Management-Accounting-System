<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\FinancialAccountType;
use App\Models\ChartOfAccount;
use App\Models\FinancialAccount;
use App\Support\ChartAccountCode;
use Illuminate\Database\Seeder;

class FinancialAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['Cash', FinancialAccountType::Cash, ChartAccountCode::Cash],
            ['DBBL Bank', FinancialAccountType::Bank, ChartAccountCode::Bank],
            ['BRAC Bank', FinancialAccountType::Bank, ChartAccountCode::Bank],
            ['bKash', FinancialAccountType::MobileWallet, ChartAccountCode::MobileWallet],
            ['Nagad', FinancialAccountType::MobileWallet, ChartAccountCode::MobileWallet],
        ];

        foreach ($accounts as [$name, $type, $code]) {
            $chart = ChartOfAccount::query()->where('code', $code)->firstOrFail();

            $account = FinancialAccount::query()->firstOrNew(['name' => $name]);
            $account->fill([
                'type' => $type,
                'chart_of_account_id' => $chart->id,
                'is_active' => true,
                'is_system' => true,
            ]);

            if (! $account->exists) {
                $account->opening_balance = '0.00';
                $account->current_balance = '0.00';
            }

            $account->save();
        }
    }
}
