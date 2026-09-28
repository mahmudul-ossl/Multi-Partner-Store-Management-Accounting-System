<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Exceptions\ApprovalStateException;
use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use App\Models\PartnerInvestment;
use App\Models\PartnerTransfer;
use App\Models\PartnerWithdrawal;
use App\Models\User;
use App\Support\ChartAccountCode;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Posts partner capital movements. Called only after final approval.
 */
final class PartnerFinancePoster
{
    public function __construct(private readonly JournalEntryService $journal) {}

    public function post(Model $document, User $actor): void
    {
        $entry = match (true) {
            $document instanceof PartnerInvestment => $this->investment($document, $actor),
            $document instanceof PartnerWithdrawal => $this->withdrawal($document, $actor),
            $document instanceof PartnerTransfer => $this->transfer($document, $actor),
            default => throw new ApprovalStateException('This approval type does not post a journal in this phase.'),
        };

        $document->journal_entry_id = $entry->id;
        $document->save();
    }

    public function reverse(Model $document, User $actor, string $reason): void
    {
        if (! isset($document->journal_entry_id) || $document->journal_entry_id === null) {
            throw new ApprovalStateException('This document has no journal entry to reverse.');
        }

        $document->load('journalEntry');
        $this->journal->reverse($document->journalEntry, $actor, $reason);
    }

    private function investment(PartnerInvestment $document, User $actor): JournalEntry
    {
        $document->load('partner', 'financialAccount.chartOfAccount');
        $amount = $this->amount($document->amount);
        $account = $this->account($document->financialAccount);
        $description = 'Investment '.$document->reference.' · '.$document->partner->name;

        return $this->journal->post($document, $actor, $document->transaction_date->toDateString(), $description, [
            [
                'account_code' => $account->chartOfAccount->code,
                'financial_account_id' => $account->id,
                'debit' => $amount,
                'credit' => '0.00',
                'description' => $description,
            ],
            [
                'account_code' => ChartAccountCode::PartnerCapital,
                'partner_id' => $document->partner_id,
                'debit' => '0.00',
                'credit' => $amount,
                'description' => $description,
            ],
        ]);
    }

    private function withdrawal(PartnerWithdrawal $document, User $actor): JournalEntry
    {
        $document->load('partner', 'financialAccount.chartOfAccount');
        $amount = $this->amount($document->amount);
        $account = $this->account($document->financialAccount);
        $description = 'Withdrawal '.$document->reference.' · '.$document->partner->name;

        return $this->journal->post($document, $actor, $document->transaction_date->toDateString(), $description, [
            [
                'account_code' => ChartAccountCode::PartnerWithdrawals,
                'partner_id' => $document->partner_id,
                'debit' => $amount,
                'credit' => '0.00',
                'description' => $description,
            ],
            [
                'account_code' => $account->chartOfAccount->code,
                'financial_account_id' => $account->id,
                'debit' => '0.00',
                'credit' => $amount,
                'description' => $description,
            ],
        ]);
    }

    private function transfer(PartnerTransfer $document, User $actor): JournalEntry
    {
        $document->load('fromPartner', 'toPartner');
        $amount = $this->amount($document->amount);
        $description = 'Transfer '.$document->reference.' · '.$document->fromPartner->name.' to '.$document->toPartner->name;

        return $this->journal->post($document, $actor, $document->transaction_date->toDateString(), $description, [
            [
                'account_code' => ChartAccountCode::PartnerCapital,
                'partner_id' => $document->from_partner_id,
                'debit' => $amount,
                'credit' => '0.00',
                'description' => 'Transfer to '.$document->toPartner->name,
            ],
            [
                'account_code' => ChartAccountCode::PartnerCapital,
                'partner_id' => $document->to_partner_id,
                'debit' => '0.00',
                'credit' => $amount,
                'description' => 'Transfer from '.$document->fromPartner->name,
            ],
        ]);
    }

    private function amount(mixed $amount): string
    {
        $money = Money::of(is_int($amount) ? $amount : (string) $amount);

        if ($money->compare('0.00') !== 1) {
            throw new ApprovalStateException('The amount must be greater than zero.');
        }

        return $money->amount();
    }

    private function account(?FinancialAccount $account): FinancialAccount
    {
        if (! $account instanceof FinancialAccount || ! $account->is_active) {
            throw new ApprovalStateException('The financial account is not available.');
        }

        return $account;
    }
}
