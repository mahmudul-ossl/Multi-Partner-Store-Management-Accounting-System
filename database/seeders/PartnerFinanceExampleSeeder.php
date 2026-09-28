<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FinancialAccount;
use App\Models\Partner;
use App\Models\PartnerInvestment;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\Finance\InvestmentService;
use App\Services\Finance\TransferService;
use App\Services\Finance\WithdrawalService;
use Illuminate\Database\Seeder;

/**
 * Example movements go through the same services the UI uses, so journals
 * exist only for fully approved documents.
 */
class PartnerFinanceExampleSeeder extends Seeder
{
    public function run(): void
    {
        if (PartnerInvestment::query()->exists()) {
            return;
        }

        $investments = app(InvestmentService::class);
        $withdrawals = app(WithdrawalService::class);
        $transfers = app(TransferService::class);
        $approvals = app(ApprovalService::class);

        $admin = User::query()->where('email', 'admin@mpstore.test')->firstOrFail();
        $accountant = User::query()->where('email', 'accountant@mpstore.test')->firstOrFail();
        $superAdmin = User::query()->where('email', (string) config('mpstore.seed.super_admin_email'))->firstOrFail();
        $rahim = User::query()->where('email', 'rahim.uddin@mpstore.test')->firstOrFail();
        $karim = User::query()->where('email', 'karim.hossain@mpstore.test')->firstOrFail();
        $nusrat = User::query()->where('email', 'nusrat.jahan@mpstore.test')->firstOrFail();

        $cash = FinancialAccount::query()->where('name', 'Cash')->firstOrFail();
        $dbbl = FinancialAccount::query()->where('name', 'DBBL Bank')->firstOrFail();

        $rahimPartner = Partner::query()->where('partner_code', 'P-0001')->firstOrFail();
        $fatema = Partner::query()->where('partner_code', 'P-0002')->firstOrFail();
        $karimPartner = Partner::query()->where('partner_code', 'P-0003')->firstOrFail();
        $nusratPartner = Partner::query()->where('partner_code', 'P-0004')->firstOrFail();
        $shahidul = Partner::query()->where('partner_code', 'P-0005')->firstOrFail();

        $small = $investments->create($rahim, [
            'partner_id' => $rahimPartner->id,
            'amount' => '8000.00',
            'transaction_date' => '2026-01-15',
            'payment_method' => 'cash',
            'financial_account_id' => $cash->id,
            'note' => 'Opening cash contribution.',
        ]);
        $approvals->approve($small->approvalRequest, $accountant, 'Received in cash.');

        $second = $investments->create($admin, [
            'partner_id' => $fatema->id,
            'amount' => '25000.00',
            'transaction_date' => '2026-02-01',
            'payment_method' => 'bank',
            'financial_account_id' => $dbbl->id,
            'note' => 'Bank transfer from Fatema.',
        ]);
        $approvals->approve($second->approvalRequest, $accountant, 'Bank advice checked.');
        $approvals->approve($second->approvalRequest->fresh(), $superAdmin, 'Second approval.');

        $withdrawal = $withdrawals->create($karim, [
            'partner_id' => $karimPartner->id,
            'amount' => '4000.00',
            'transaction_date' => '2026-03-04',
            'reason' => 'Personal drawing',
            'payment_method' => 'cash',
            'financial_account_id' => $cash->id,
            'note' => null,
        ]);
        $approvals->approve($withdrawal->approvalRequest, $accountant, 'Within the single-approval band.');

        $withdrawals->create($nusrat, [
            'partner_id' => $nusratPartner->id,
            'amount' => '20000.00',
            'transaction_date' => '2026-03-18',
            'reason' => 'Family expense',
            'payment_method' => 'bank',
            'financial_account_id' => $dbbl->id,
            'note' => 'Waiting for a second approver.',
        ]);

        $rejected = $withdrawals->create($admin, [
            'partner_id' => $shahidul->id,
            'amount' => '15000.00',
            'transaction_date' => '2026-04-02',
            'reason' => 'Advance',
            'payment_method' => 'cash',
            'financial_account_id' => $cash->id,
            'note' => 'Requested by phone.',
        ]);
        $approvals->reject($rejected->approvalRequest, $accountant, 'Supporting note is missing.');

        $transfer = $transfers->create($admin, [
            'from_partner_id' => $rahimPartner->id,
            'to_partner_id' => $fatema->id,
            'amount' => '3000.00',
            'transaction_date' => '2026-04-20',
            'note' => 'Reallocate part of Rahim\'s capital.',
        ]);
        $approvals->approve($transfer->approvalRequest, $accountant, 'Capital transfer only.');

        $large = $investments->create($admin, [
            'partner_id' => $karimPartner->id,
            'amount' => '150000.00',
            'transaction_date' => '2026-05-01',
            'payment_method' => 'bank',
            'financial_account_id' => $dbbl->id,
            'note' => 'Needs three distinct approvers.',
        ]);
        $approvals->approve($large->approvalRequest, $accountant, 'First of three.');
    }
}
