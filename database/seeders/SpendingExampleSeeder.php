<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Partner;
use App\Models\Promotion;
use App\Models\PromotionPartnerExpense;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\Finance\PartnerStatementService;
use App\Services\Spending\ExpenseService;
use App\Services\Spending\PromotionContributionService;
use App\Services\Spending\PromotionService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Example promotions and expenses go through the same services as the screens.
 * A pending contribution does not move cash or partner capital.
 */
class SpendingExampleSeeder extends Seeder
{
    public function run(): void
    {
        if (Promotion::query()->exists()) {
            return;
        }

        $sales = User::query()->where('email', 'sales@mpstore.test')->firstOrFail();
        $rahimUser = User::query()->where('email', 'rahim.uddin@mpstore.test')->firstOrFail();
        $accountant = User::query()->where('email', 'accountant@mpstore.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@mpstore.test')->firstOrFail();
        $cash = FinancialAccount::query()->where('name', 'Cash')->firstOrFail();
        $rahim = Partner::query()->where('partner_code', 'P-0001')->firstOrFail();
        $fatema = Partner::query()->where('partner_code', 'P-0002')->firstOrFail();
        $promotions = app(PromotionService::class);
        $contributions = app(PromotionContributionService::class);
        $expenses = app(ExpenseService::class);
        $approvals = app(ApprovalService::class);

        $facebook = $promotions->create($sales, [
            'name' => 'Boishakh Facebook ads',
            'platform' => 'facebook',
            'starts_on' => '2026-04-01',
            'ends_on' => '2026-04-30',
            'budget' => '8000.00',
            'status' => 'active',
            'description' => 'Sponsored posts for the Pohela Boishakh leather range.',
        ]);
        $banner = $promotions->create($sales, [
            'name' => 'Shopfront banner',
            'platform' => 'offline',
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-06-30',
            'budget' => '3000.00',
            'status' => 'active',
            'description' => 'Printed banner outside the Dhanmondi shop.',
        ]);

        $personal = $contributions->create($rahimUser, $facebook, [
            'partner_id' => $rahim->id,
            'funded_by' => 'partner',
            'amount' => '2000.00',
            'transaction_date' => '2026-04-10',
            'note' => 'Rahim paid the ad account from personal bKash.',
        ]);
        $approvals->approve($personal->approvalRequest, $accountant, 'Personal contribution accepted.');

        $business = $contributions->create($sales, $banner, [
            'partner_id' => $rahim->id,
            'funded_by' => 'business',
            'amount' => '500.00',
            'transaction_date' => '2026-05-12',
            'payment_method' => 'cash',
            'financial_account_id' => $cash->id,
            'note' => 'Printer paid from the shop cash drawer.',
        ]);
        $approvals->approve($business->approvalRequest, $accountant, 'Banner paid.');

        $pending = $contributions->create($sales, $facebook, [
            'partner_id' => $fatema->id,
            'funded_by' => 'business',
            'amount' => '1500.00',
            'transaction_date' => '2026-04-20',
            'payment_method' => 'cash',
            'financial_account_id' => $cash->id,
            'note' => 'Boost still waiting for approval.',
        ]);

        $rent = $expenses->create($accountant, [
            'category' => 'rent',
            'amount' => '800.00',
            'transaction_date' => '2026-05-01',
            'description' => 'May shop rent, paid from cash.',
            'payment_method' => 'cash',
            'financial_account_id' => $cash->id,
        ]);
        $approvals->approve($rent->approvalRequest, $admin, 'Rent posted.');

        $power = $expenses->create($accountant, [
            'category' => 'electricity',
            'amount' => '400.00',
            'transaction_date' => '2026-05-15',
            'description' => 'Fatema paid the shop electricity bill personally.',
            'partner_id' => $fatema->id,
        ]);
        $approvals->approve($power->approvalRequest, $admin, 'Reimburse through capital.');

        $packaging = $expenses->create($accountant, [
            'category' => 'packaging',
            'amount' => '250.00',
            'transaction_date' => '2026-05-18',
            'description' => 'Gift boxes still waiting for approval.',
            'payment_method' => 'cash',
            'financial_account_id' => $cash->id,
        ]);

        $facebook->refresh();
        $banner->refresh();
        $pending->refresh();
        $packaging->refresh();

        $rahimPromotion = app(PartnerStatementService::class)->position($rahim)['promotion_contribution']['amount'];
        $fatemaExpense = app(PartnerStatementService::class)->position($fatema)['expenses']['amount'];

        if ((string) $facebook->actual_amount !== '2000.00' || (string) $banner->actual_amount !== '500.00') {
            throw new RuntimeException('Approved contributions did not update promotion actual spend.');
        }

        if ($pending->status !== DocumentStatus::Pending || $pending->journal_entry_id !== null) {
            throw new RuntimeException('The pending promotion contribution should not have a journal.');
        }

        if ($packaging->status !== DocumentStatus::Pending || $packaging->journal_entry_id !== null) {
            throw new RuntimeException('The pending expense should not have a journal.');
        }

        if ($rahimPromotion !== '2000.00' || $fatemaExpense !== '400.00') {
            throw new RuntimeException('Partner statements did not pick up the approved spending.');
        }

        if (PromotionPartnerExpense::query()->where('status', DocumentStatus::Approved)->count() !== 2) {
            throw new RuntimeException('Expected two approved promotion contributions.');
        }

        if (Expense::query()->where('status', DocumentStatus::Approved)->count() !== 2) {
            throw new RuntimeException('Expected two approved expenses.');
        }
    }
}
