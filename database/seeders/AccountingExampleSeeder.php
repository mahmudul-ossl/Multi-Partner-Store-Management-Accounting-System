<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AccountTransfer;
use App\Models\ChartOfAccount;
use App\Models\FinancialAccount;
use App\Models\ManualJournal;
use App\Models\User;
use App\Services\Accounting\AccountTransferService;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\FinancialAccountService;
use App\Services\Accounting\ManualJournalService;
use App\Services\Approvals\ApprovalService;
use App\Support\ChartAccountCode;
use Illuminate\Database\Seeder;

/**
 * Example accounting documents go through the same services as the UI.
 * Opening balances post immediately. Journals and transfers wait for approval.
 */
class AccountingExampleSeeder extends Seeder
{
    public function run(): void
    {
        if (ManualJournal::query()->exists() || AccountTransfer::query()->exists()) {
            return;
        }

        $admin = User::query()->where('email', 'admin@mpstore.test')->firstOrFail();
        $accountant = User::query()->where('email', 'accountant@mpstore.test')->firstOrFail();
        $cash = FinancialAccount::query()->where('name', 'Cash')->firstOrFail();
        $dbbl = FinancialAccount::query()->where('name', 'DBBL Bank')->firstOrFail();
        $bkash = FinancialAccount::query()->where('name', 'bKash')->firstOrFail();
        $parent = ChartOfAccount::query()->where('code', ChartAccountCode::Cash)->firstOrFail();

        $pettyChart = ChartOfAccount::query()->where('code', '1001')->first();

        if (! $pettyChart instanceof ChartOfAccount) {
            $pettyChart = app(ChartOfAccountService::class)->create($admin, [
                'code' => '1001',
                'name' => 'Petty Cash',
                'type' => 'asset',
                'normal_balance' => 'debit',
                'parent_id' => $parent->id,
                'is_active' => true,
                'description' => 'Till float held at the counter.',
            ]);
        }

        if (! FinancialAccount::query()->where('name', 'Petty Cash')->exists()) {
            app(FinancialAccountService::class)->create($admin, [
                'name' => 'Petty Cash',
                'type' => 'cash',
                'chart_of_account_id' => $pettyChart->id,
                'opening_balance' => '5000.00',
                'opening_date' => '2026-01-01',
            ]);
        }

        $journals = app(ManualJournalService::class);
        $approvals = app(ApprovalService::class);

        $charges = $journals->create($accountant, [
            'entry_date' => '2026-04-02',
            'description' => 'April bank charges',
            'lines' => [
                [
                    'account_code' => ChartAccountCode::BankCharges,
                    'debit' => '1500.00',
                    'credit' => '0.00',
                    'description' => 'Bank charges',
                ],
                [
                    'account_code' => ChartAccountCode::Cash,
                    'financial_account_id' => $cash->id,
                    'debit' => '0.00',
                    'credit' => '1500.00',
                    'description' => 'Paid from cash',
                ],
            ],
        ]);
        $approvals->approve($charges->approvalRequest, $admin, 'Bank charge accepted.');

        $journals->create($accountant, [
            'entry_date' => '2026-04-10',
            'description' => 'Pending rent accrual',
            'lines' => [
                [
                    'account_code' => ChartAccountCode::Rent,
                    'debit' => '2000.00',
                    'credit' => '0.00',
                    'description' => 'April rent',
                ],
                [
                    'account_code' => ChartAccountCode::OtherPayables,
                    'debit' => '0.00',
                    'credit' => '2000.00',
                    'description' => 'Rent payable',
                ],
            ],
        ]);

        $transfer = app(AccountTransferService::class)->create($admin, [
            'from_financial_account_id' => $dbbl->id,
            'to_financial_account_id' => $bkash->id,
            'amount' => '2000.00',
            'transaction_date' => '2026-04-12',
            'note' => 'Move funds to bKash.',
        ]);
        $approvals->approve($transfer->approvalRequest, $accountant, 'Wallet top-up confirmed.');
    }
}
