<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\AccountType;
use App\Enums\AuditAction;
use App\Enums\FinancialAccountType;
use App\Exceptions\ApprovalStateException;
use App\Models\ChartOfAccount;
use App\Models\FinancialAccount;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Ledger\JournalEntryService;
use App\Support\ChartAccountCode;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

final class FinancialAccountService
{
    public function __construct(
        private readonly JournalEntryService $journal,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array{name: string, type: string, chart_of_account_id: int, opening_balance?: string|int, opening_date?: string|null}  $attributes
     */
    public function create(User $actor, array $attributes): FinancialAccount
    {
        return DB::transaction(function () use ($actor, $attributes): FinancialAccount {
            $chart = ChartOfAccount::query()->find($attributes['chart_of_account_id']);

            if (! $chart instanceof ChartOfAccount || ! $chart->is_active || $chart->type !== AccountType::Asset) {
                throw new ApprovalStateException('Cash, bank, and wallet accounts must use an active asset ledger account.');
            }

            $opening = Money::of($attributes['opening_balance'] ?? '0')->amount();

            if (Money::of($opening)->compare('0.00') === -1) {
                throw new ApprovalStateException('Opening balance cannot be negative.');
            }

            $account = FinancialAccount::query()->create([
                'name' => $attributes['name'],
                'type' => FinancialAccountType::from($attributes['type']),
                'chart_of_account_id' => $chart->id,
                'opening_balance' => $opening,
                'current_balance' => '0.00',
                'is_active' => true,
                'is_system' => false,
            ]);

            if (Money::of($opening)->compare('0.00') === 1) {
                $this->postOpening($account, $actor, $attributes['opening_date'] ?? now()->toDateString());
            }

            $this->audit->record(AuditAction::Created, $account, null, [
                'name' => $account->name,
                'opening_balance' => $opening,
                'current_balance' => (string) $account->fresh()->current_balance,
            ], $actor);

            return $account->fresh(['chartOfAccount']);
        });
    }

    /**
     * @param  array{name: string, is_active?: bool}  $attributes
     */
    public function update(FinancialAccount $account, User $actor, array $attributes): FinancialAccount
    {
        return DB::transaction(function () use ($account, $actor, $attributes): FinancialAccount {
            $account = FinancialAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $before = ['name' => $account->name, 'is_active' => $account->is_active];
            $isActive = array_key_exists('is_active', $attributes)
                ? (bool) $attributes['is_active']
                : (bool) $account->is_active;

            if ($account->is_system && $isActive !== (bool) $account->is_active) {
                throw new ApprovalStateException('System account active flag cannot be changed.');
            }

            $account->fill([
                'name' => $attributes['name'],
                'is_active' => $account->is_system ? $account->is_active : $isActive,
            ]);
            $account->save();

            $this->audit->record(AuditAction::Updated, $account, $before, [
                'name' => $account->name,
                'is_active' => $account->is_active,
            ], $actor);

            return $account;
        });
    }

    public function ledgerBalance(FinancialAccount $account): string
    {
        $balance = '0.00';

        JournalEntryLine::query()
            ->where('financial_account_id', $account->id)
            ->orderBy('id')
            ->each(function (JournalEntryLine $line) use (&$balance): void {
                $balance = Money::of($balance)->add((string) $line->debit)->sub((string) $line->credit)->amount();
            });

        return $balance;
    }

    public function syncFromLedger(FinancialAccount $account, User $actor): FinancialAccount
    {
        return DB::transaction(function () use ($account, $actor): FinancialAccount {
            $locked = FinancialAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $before = (string) $locked->current_balance;
            $ledger = $this->ledgerBalance($locked);
            $locked->current_balance = $ledger;
            $locked->save();

            $this->audit->record(AuditAction::Updated, $locked, [
                'current_balance' => $before,
            ], [
                'current_balance' => $ledger,
            ], $actor);

            return $locked;
        });
    }

    private function postOpening(FinancialAccount $account, User $actor, string $date): void
    {
        $equity = ChartOfAccount::query()->where('code', ChartAccountCode::OpeningBalanceEquity)->first();

        if (! $equity instanceof ChartOfAccount || ! $equity->is_active) {
            throw new ApprovalStateException('Opening balance equity account 3300 is not available.');
        }

        $account->load('chartOfAccount');
        $amount = (string) $account->opening_balance;

        $this->journal->post($account, $actor, $date, 'Opening balance · '.$account->name, [
            [
                'account_code' => $account->chartOfAccount->code,
                'financial_account_id' => $account->id,
                'debit' => $amount,
                'credit' => '0.00',
                'description' => 'Opening balance · '.$account->name,
            ],
            [
                'account_code' => ChartAccountCode::OpeningBalanceEquity,
                'debit' => '0.00',
                'credit' => $amount,
                'description' => 'Opening balance equity · '.$account->name,
            ],
        ]);
    }
}
