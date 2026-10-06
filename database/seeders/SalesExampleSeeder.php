<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Models\FinancialAccount;
use App\Models\SalesIncome;
use App\Models\User;
use App\Services\Sales\SalesIncomeService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Example website and shop takings go through the same services as the screens.
 */
class SalesExampleSeeder extends Seeder
{
    public function run(): void
    {
        if (SalesIncome::query()->exists()) {
            return;
        }

        $admin = User::query()->where('email', (string) config('mpstore.seed.super_admin_email'))->firstOrFail();
        $seller = User::query()->where('email', 'sales@mpstore.test')->firstOrFail();
        $cash = FinancialAccount::query()->where('name', 'Cash')->firstOrFail();
        $incomes = app(SalesIncomeService::class);

        $incomes->create($admin, [
            'source' => 'shop',
            'amount' => '1450.00',
            'transaction_date' => '2026-06-02',
            'payment_method' => 'cash',
            'financial_account_id' => $cash->id,
            'note' => 'Shop till for 2 June.',
        ]);

        $incomes->create($admin, [
            'source' => 'website',
            'amount' => '1730.00',
            'transaction_date' => '2026-06-04',
            'payment_method' => 'cash',
            'financial_account_id' => $cash->id,
            'note' => 'Website orders for 4 June.',
        ]);

        $pending = $incomes->create($seller, [
            'source' => 'other',
            'amount' => '2700.00',
            'transaction_date' => '2026-06-10',
            'payment_method' => 'cash',
            'financial_account_id' => $cash->id,
            'note' => 'Waiting for approval.',
        ]);

        $pending->refresh();

        if ($pending->status !== DocumentStatus::Pending || $pending->journal_entry_id !== null || SalesIncome::query()->where('status', DocumentStatus::Approved)->count() !== 2) {
            throw new RuntimeException('The example sales income seed did not settle as expected.');
        }
    }
}
