<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Models\AccountingPeriod;
use App\Models\Partner;
use App\Models\ProfitAllocation;
use App\Models\User;
use App\Services\Accounting\AllocationService;
use App\Services\Accounting\PeriodGuard;
use App\Services\Accounting\PeriodService;
use App\Services\Approvals\ApprovalService;
use App\Services\Finance\PartnerStatementService;
use App\Support\Money;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * One ownership allocation of ৳770 among the active partners, then a close
 * through 31 May 2026. 770 divided by the active ownership weights (77) is exact.
 */
class AllocationExampleSeeder extends Seeder
{
    public function run(): void
    {
        if (ProfitAllocation::query()->exists()) {
            return;
        }

        $accountant = User::query()->where('email', 'accountant@mpstore.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@mpstore.test')->firstOrFail();
        $rahim = Partner::query()->where('partner_code', 'P-0001')->firstOrFail();
        $allocations = app(AllocationService::class);
        $approvals = app(ApprovalService::class);

        $allocation = $allocations->create($accountant, [
            'amount' => '770.00',
            'transaction_date' => '2026-06-30',
            'method' => 'ownership',
            'note' => 'Sample profit share for the active partners.',
        ]);
        $approvals->approve($allocation->approvalRequest, $admin, 'Ownership share posted.');

        app(PeriodService::class)->close($admin, '2026-05-31', 'Close the books through May before the sample allocation date.');

        $allocation->refresh()->load('lines', 'journalEntry.lines');
        $sum = '0.00';
        $rahimLine = null;

        foreach ($allocation->lines as $line) {
            $sum = Money::of($sum)->add((string) $line->amount)->amount();
            if ((int) $line->partner_id === (int) $rahim->id) {
                $rahimLine = (string) $line->amount;
            }
        }

        $position = app(PartnerStatementService::class)->position($rahim);

        if ((string) $allocation->amount !== '770.00' || $allocation->status !== DocumentStatus::Approved) {
            throw new RuntimeException('The sample allocation was not approved.');
        }

        if ($sum !== '770.00' || $rahimLine !== '180.00') {
            throw new RuntimeException('Ownership shares did not come out to 770 with Rahim at 180.');
        }

        if ($allocation->journalEntry === null) {
            throw new RuntimeException('The sample allocation has no journal.');
        }

        $debit = '0.00';
        $credit = '0.00';

        foreach ($allocation->journalEntry->lines as $line) {
            $debit = Money::of($debit)->add((string) $line->debit)->amount();
            $credit = Money::of($credit)->add((string) $line->credit)->amount();
        }

        if ($debit !== $credit) {
            throw new RuntimeException('The sample allocation journal does not balance.');
        }

        if ($position['profit_share']['amount'] !== '180.00' || $position['current_capital']['amount'] !== '7180.00') {
            throw new RuntimeException('Rahim capital after allocation was '.$position['current_capital']['amount'].' with profit share '.$position['profit_share']['amount'].'.');
        }

        if (app(PeriodGuard::class)->closedThrough() !== '2026-05-31' || AccountingPeriod::query()->count() !== 1) {
            throw new RuntimeException('The books were not closed through 31 May 2026.');
        }
    }
}
