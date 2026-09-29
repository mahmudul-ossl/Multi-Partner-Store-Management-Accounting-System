<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\DocumentStatus;
use App\Exceptions\ApprovalStateException;
use App\Models\AccountTransfer;
use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use App\Models\ManualJournal;
use App\Models\PartnerInvestment;
use App\Models\PartnerTransfer;
use App\Models\PartnerWithdrawal;
use App\Models\User;
use App\Services\Ledger\JournalEntryService;
use Illuminate\Support\Facades\DB;

final class JournalReversalService
{
    public function __construct(private readonly JournalEntryService $journal) {}

    public function reverse(JournalEntry $entry, User $actor, string $reason): JournalEntry
    {
        return DB::transaction(function () use ($entry, $actor, $reason): JournalEntry {
            $entry = JournalEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            $entry->load('source');
            $source = $entry->source;

            if ($source instanceof PartnerInvestment || $source instanceof PartnerWithdrawal || $source instanceof PartnerTransfer) {
                throw new ApprovalStateException('Reverse this entry from the partner document.');
            }

            $allowed = $source instanceof ManualJournal
                || $source instanceof AccountTransfer
                || $source instanceof FinancialAccount;

            if (! $allowed || $entry->reversal_of !== null) {
                throw new ApprovalStateException('This journal cannot be reversed from the ledger screen.');
            }

            $reversal = $this->journal->reverse($entry, $actor, $reason);

            if (($source instanceof ManualJournal || $source instanceof AccountTransfer)
                && $source->status === DocumentStatus::Approved
                && (int) $source->journal_entry_id === (int) $entry->id) {
                $source->status = DocumentStatus::Reversed;
                $source->save();
            }

            return $reversal;
        });
    }
}
