<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\DocumentStatus;
use App\Models\AccountTransfer;
use App\Models\ChartOfAccount;
use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\ManualJournal;
use App\Models\ManualJournalLine;
use App\Models\PartnerInvestment;
use App\Models\PartnerTransfer;
use App\Models\PartnerWithdrawal;
use App\Support\Format;
use App\Support\Money;

final class AccountingPresenter
{
    public function __construct(private readonly FinancialAccountService $accounts) {}

    /**
     * @param  array<int, array{id: int, name: string}>  $parents
     * @return array<string, mixed>
     */
    public function chartAccount(ChartOfAccount $account, int $depth = 0): array
    {
        return [
            'id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
            'type' => ['value' => $account->type->value, 'label' => $account->type->label()],
            'normal_balance' => ['value' => $account->normal_balance->value, 'label' => $account->normal_balance->label()],
            'parent_id' => $account->parent_id,
            'parent_name' => $account->parent ? $account->parent->code.' '.$account->parent->name : null,
            'depth' => $depth,
            'is_active' => $account->is_active,
            'is_system' => $account->is_system,
            'description' => $account->description,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function financialAccount(FinancialAccount $account, bool $withLedger = false): array
    {
        $payload = [
            'id' => $account->id,
            'name' => $account->name,
            'type' => ['value' => $account->type->value, 'label' => $account->type->label()],
            'chart' => $account->chartOfAccount ? [
                'id' => $account->chartOfAccount->id,
                'code' => $account->chartOfAccount->code,
                'name' => $account->chartOfAccount->name,
            ] : null,
            'opening_balance' => $this->money((string) $account->opening_balance),
            'current_balance' => $this->money((string) $account->current_balance),
            'is_active' => $account->is_active,
            'is_system' => $account->is_system,
        ];

        if ($withLedger) {
            $ledger = $this->accounts->ledgerBalance($account);
            $payload['ledger_balance'] = $this->money($ledger);
            $payload['reconciled'] = Money::of((string) $account->current_balance)->compare($ledger) === 0;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function manualJournal(ManualJournal $journal): array
    {
        $journal->loadMissing('author', 'approvalRequest', 'journalEntry');

        return [
            'id' => $journal->id,
            'reference' => $journal->reference,
            'entry_date' => $journal->entry_date?->toDateString(),
            'entry_date_formatted' => Format::date($journal->entry_date),
            'description' => $journal->description,
            'amount' => $this->money((string) $journal->amount),
            'status' => $this->status($journal->status),
            'author' => $journal->author?->name,
            'journal_entry_id' => $journal->journal_entry_id,
            'approval' => $this->approval($journal),
            'lines' => $journal->relationLoaded('lines')
                ? $journal->lines->map(fn (ManualJournalLine $line): array => [
                    'id' => $line->id,
                    'account_code' => $line->account?->code,
                    'account_name' => $line->account?->name,
                    'partner' => $line->partner?->name,
                    'financial_account' => $line->financialAccount?->name,
                    'financial_account_id' => $line->financial_account_id,
                    'partner_id' => $line->partner_id,
                    'debit' => $this->money((string) $line->debit),
                    'credit' => $this->money((string) $line->credit),
                    'description' => $line->description,
                ])->all()
                : [],
            'editable' => $journal->isEditable(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function accountTransfer(AccountTransfer $transfer): array
    {
        $transfer->loadMissing('fromAccount', 'toAccount', 'author', 'approvalRequest');

        return [
            'id' => $transfer->id,
            'reference' => $transfer->reference,
            'transaction_date' => $transfer->transaction_date?->toDateString(),
            'transaction_date_formatted' => Format::date($transfer->transaction_date),
            'amount' => $this->money((string) $transfer->amount),
            'from_account' => $transfer->fromAccount?->name,
            'to_account' => $transfer->toAccount?->name,
            'from_financial_account_id' => $transfer->from_financial_account_id,
            'to_financial_account_id' => $transfer->to_financial_account_id,
            'note' => $transfer->note,
            'status' => $this->status($transfer->status),
            'author' => $transfer->author?->name,
            'journal_entry_id' => $transfer->journal_entry_id,
            'approval' => $this->approval($transfer),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function journalEntry(JournalEntry $entry, bool $withLines = true): array
    {
        $entry->loadMissing('author', 'source');

        if ($withLines) {
            $entry->loadMissing('lines.account', 'lines.partner', 'lines.financialAccount');
        }

        return [
            'id' => $entry->id,
            'reference' => $entry->reference,
            'entry_date' => $entry->entry_date?->toDateString(),
            'entry_date_formatted' => Format::date($entry->entry_date),
            'description' => $entry->description,
            'status' => $this->status($entry->status),
            'author' => $entry->author?->name,
            'source_label' => $this->sourceLabel($entry),
            'reversal_of' => $entry->reversal_of,
            'can_reverse_source' => $this->canReverseSource($entry),
            'lines' => $entry->relationLoaded('lines')
                ? $entry->lines->map(fn (JournalEntryLine $line): array => [
                    'id' => $line->id,
                    'account' => trim(($line->account?->code ?? '').' '.($line->account?->name ?? '')),
                    'partner' => $line->partner?->name,
                    'financial_account' => $line->financialAccount?->name,
                    'debit' => $this->money((string) $line->debit),
                    'credit' => $this->money((string) $line->credit),
                    'description' => $line->description,
                ])->all()
                : [],
        ];
    }

    public function canReverseSource(JournalEntry $entry): bool
    {
        if ($entry->status !== DocumentStatus::Completed || $entry->reversal_of !== null) {
            return false;
        }

        $source = $entry->relationLoaded('source') ? $entry->source : $entry->source()->first();

        return $source instanceof ManualJournal
            || $source instanceof AccountTransfer
            || $source instanceof FinancialAccount;
    }

    public function sourceLabel(JournalEntry $entry): string
    {
        $source = $entry->relationLoaded('source') ? $entry->source : $entry->source()->first();

        return match (true) {
            $source instanceof ManualJournal => 'Manual journal '.$source->reference,
            $source instanceof AccountTransfer => 'Account transfer '.$source->reference,
            $source instanceof FinancialAccount => 'Opening balance · '.$source->name,
            $source instanceof PartnerInvestment => 'Investment '.$source->reference,
            $source instanceof PartnerWithdrawal => 'Withdrawal '.$source->reference,
            $source instanceof PartnerTransfer => 'Partner transfer '.$source->reference,
            default => 'Journal',
        };
    }

    /**
     * @return array{amount: string, formatted: string}
     */
    public function money(string $amount): array
    {
        $money = Money::of($amount);

        return [
            'amount' => $money->amount(),
            'formatted' => $money->formatted(),
        ];
    }

    /**
     * @return array{value: string, label: string, tone: string}
     */
    private function status(DocumentStatus $status): array
    {
        return [
            'value' => $status->value,
            'label' => $status->label(),
            'tone' => $status->tone(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function approval(ManualJournal|AccountTransfer $document): ?array
    {
        $approval = $document->approvalRequest;

        if ($approval === null) {
            return null;
        }

        return [
            'id' => $approval->id,
            'completed_approvals' => (int) $approval->completed_approvals,
            'required_approvals' => (int) $approval->required_approvals,
            'status' => [
                'value' => $approval->status->value,
                'label' => $approval->status->label(),
                'tone' => $approval->status->tone(),
            ],
        ];
    }
}
