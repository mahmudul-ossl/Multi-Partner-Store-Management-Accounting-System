<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Exceptions\ApprovalStateException;
use App\Models\AccountTransfer;
use App\Models\JournalEntry;
use App\Models\ManualJournal;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Posts accounting documents after final approval.
 * Kept separate from the document services so ApprovalService does not
 * depend on the classes that call it.
 */
final class AccountingPoster
{
    public function __construct(private readonly JournalEntryService $journal) {}

    public function post(Model $document, User $actor): void
    {
        $entry = match (true) {
            $document instanceof ManualJournal => $this->manual($document, $actor),
            $document instanceof AccountTransfer => $this->transfer($document, $actor),
            default => throw new ApprovalStateException('This approval type does not post an accounting journal.'),
        };

        $document->journal_entry_id = $entry->id;
        $document->save();
    }

    private function manual(ManualJournal $document, User $actor): JournalEntry
    {
        $document->load('lines.account');

        $lines = $document->lines->map(fn ($line): array => [
            'account_code' => $line->account->code,
            'partner_id' => $line->partner_id,
            'financial_account_id' => $line->financial_account_id,
            'debit' => (string) $line->debit,
            'credit' => (string) $line->credit,
            'description' => $line->description ?: $document->description,
        ])->all();

        return $this->journal->post(
            $document,
            $actor,
            $document->entry_date->toDateString(),
            $document->description,
            $lines,
        );
    }

    private function transfer(AccountTransfer $document, User $actor): JournalEntry
    {
        $document->load('fromAccount.chartOfAccount', 'toAccount.chartOfAccount');
        $amount = Money::of((string) $document->amount)->amount();
        $description = 'Transfer '.$document->reference.' · '.$document->fromAccount->name.' to '.$document->toAccount->name;

        return $this->journal->post($document, $actor, $document->transaction_date->toDateString(), $description, [
            [
                'account_code' => $document->toAccount->chartOfAccount->code,
                'financial_account_id' => $document->to_financial_account_id,
                'debit' => $amount,
                'credit' => '0.00',
                'description' => 'Received from '.$document->fromAccount->name,
            ],
            [
                'account_code' => $document->fromAccount->chartOfAccount->code,
                'financial_account_id' => $document->from_financial_account_id,
                'debit' => '0.00',
                'credit' => $amount,
                'description' => 'Sent to '.$document->toAccount->name,
            ],
        ]);
    }
}
