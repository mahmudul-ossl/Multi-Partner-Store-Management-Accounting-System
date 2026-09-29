<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\ProfitAllocation;
use App\Models\User;
use App\Services\Ledger\JournalEntryService;
use App\Support\ChartAccountCode;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Debits retained earnings and credits each partner's capital.
 * There is no separate income-summary account.
 */
final class AllocationPoster
{
    public function __construct(private readonly JournalEntryService $journal) {}

    public function post(ProfitAllocation $document, User $actor): void
    {
        DB::transaction(function () use ($document, $actor): void {
            $document->load('lines.partner');
            $amount = Money::of((string) $document->amount)->amount();
            $description = 'Profit allocation '.$document->reference;
            $lines = [[
                'account_code' => ChartAccountCode::RetainedEarnings,
                'debit' => $amount,
                'credit' => '0.00',
                'description' => $description,
            ]];

            foreach ($document->lines as $line) {
                if (Money::of((string) $line->amount)->isZero()) {
                    continue;
                }

                $lines[] = [
                    'account_code' => ChartAccountCode::PartnerCapital,
                    'partner_id' => $line->partner_id,
                    'debit' => '0.00',
                    'credit' => Money::of((string) $line->amount)->amount(),
                    'description' => $description.' · '.($line->partner?->name ?? 'Partner'),
                ];
            }

            $entry = $this->journal->post(
                $document,
                $actor,
                $document->transaction_date->toDateString(),
                $description,
                $lines,
            );

            $document->journal_entry_id = $entry->id;
            $document->save();
        });
    }
}
