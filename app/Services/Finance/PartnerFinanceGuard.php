<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\FinancialAccountType;
use App\Enums\PaymentMethod;
use App\Models\FinancialAccount;
use App\Models\User;
use App\Services\PartnerDirectory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class PartnerFinanceGuard
{
    public function __construct(private readonly PartnerDirectory $partners) {}

    public function assertOwnPartner(User $actor, int $partnerId): void
    {
        if (! $this->partners->restrictsToOwnRecord($actor)) {
            return;
        }

        $own = $actor->partner?->id;

        if ($own === null || (int) $own !== $partnerId) {
            throw new AuthorizationException('You can only record transactions for your own partnership.');
        }
    }

    public function assertAccount(FinancialAccount $account, PaymentMethod $method): void
    {
        if (! $account->is_active) {
            throw ValidationException::withMessages([
                'financial_account_id' => 'That financial account is inactive.',
            ]);
        }

        $expected = match ($method) {
            PaymentMethod::Cash => FinancialAccountType::Cash,
            PaymentMethod::Bank, PaymentMethod::Cheque, PaymentMethod::Card => FinancialAccountType::Bank,
            PaymentMethod::Bkash, PaymentMethod::Nagad, PaymentMethod::Rocket => FinancialAccountType::MobileWallet,
        };

        if ($account->type !== $expected) {
            throw ValidationException::withMessages([
                'financial_account_id' => 'Choose a financial account that matches the payment method.',
            ]);
        }
    }
}
