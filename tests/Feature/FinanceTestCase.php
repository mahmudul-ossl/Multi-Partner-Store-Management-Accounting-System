<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\FinancialAccount;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\ApprovalThresholdSeeder;
use Database\Seeders\ChartOfAccountSeeder;
use Database\Seeders\FinancialAccountSeeder;

abstract class FinanceTestCase extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountSeeder::class);
        $this->seed(FinancialAccountSeeder::class);
        $this->seed(ApprovalThresholdSeeder::class);
    }

    /**
     * @return array{0: User, 1: Partner}
     */
    protected function linkedPartner(string $name = 'Partner A'): array
    {
        $user = $this->userWithRole(RoleName::Partner, [
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'.'.uniqid().'@mpstore.test',
        ]);

        $partner = Partner::factory()->create([
            'name' => $name,
            'user_id' => $user->id,
        ]);

        return [$user, $partner];
    }

    protected function cashAccount(): FinancialAccount
    {
        return FinancialAccount::query()->where('name', 'Cash')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    protected function withdrawalPayload(Partner $partner, string $amount = '50000.00', array $overrides = []): array
    {
        return array_merge([
            'partner_id' => $partner->id,
            'amount' => $amount,
            'transaction_date' => '2026-09-28',
            'reason' => 'Personal drawing',
            'payment_method' => 'cash',
            'financial_account_id' => $this->cashAccount()->id,
            'note' => 'Test withdrawal',
        ], $overrides);
    }
}
