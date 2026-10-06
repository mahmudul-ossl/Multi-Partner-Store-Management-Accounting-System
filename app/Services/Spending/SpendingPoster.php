<?php

declare(strict_types=1);

namespace App\Services\Spending;

use App\Enums\FundingSource;
use App\Exceptions\ApprovalStateException;
use App\Models\Expense;
use App\Models\Promotion;
use App\Models\PromotionPartnerExpense;
use App\Models\User;
use App\Services\Ledger\JournalEntryService;
use App\Support\ChartAccountCode;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Posts promotion contributions and operating expenses after final approval.
 * Partner-paid amounts credit that partner's capital. Business-paid amounts
 * credit cash or bank. Customer delivery on a sale stays in product sales.
 */
final class SpendingPoster
{
    public function __construct(private readonly JournalEntryService $journal) {}

    public function post(Model $document, User $actor): void
    {
        match (true) {
            $document instanceof PromotionPartnerExpense => $this->contribution($document, $actor),
            $document instanceof Expense => $this->expense($document, $actor),
            default => throw new ApprovalStateException('This approval type does not post spending.'),
        };
    }

    private function contribution(PromotionPartnerExpense $document, User $actor): void
    {
        DB::transaction(function () use ($document, $actor): void {
            $document->load('promotion', 'partner', 'financialAccount.chartOfAccount');
            $amount = Money::of((string) $document->amount)->amount();
            $description = 'Promotion '.$document->reference.' · '.$document->promotion->name;
            $lines = [[
                'account_code' => ChartAccountCode::PromotionExpense,
                'partner_id' => $document->partner_id,
                'debit' => $amount,
                'credit' => '0.00',
                'description' => $description,
            ]];

            if ($document->funded_by === FundingSource::Partner) {
                $lines[] = [
                    'account_code' => ChartAccountCode::PartnerCapital,
                    'partner_id' => $document->partner_id,
                    'debit' => '0.00',
                    'credit' => $amount,
                    'description' => $description,
                ];
            } else {
                $account = $document->financialAccount;
                if ($account === null) {
                    throw new ApprovalStateException('A business-paid contribution needs a financial account.');
                }

                $lines[] = [
                    'account_code' => $account->chartOfAccount->code,
                    'financial_account_id' => $account->id,
                    'debit' => '0.00',
                    'credit' => $amount,
                    'description' => $description,
                ];
            }

            $entry = $this->journal->post($document, $actor, $document->transaction_date->toDateString(), $description, $lines);
            $document->journal_entry_id = $entry->id;
            $document->save();

            $promotion = Promotion::query()->whereKey($document->promotion_id)->lockForUpdate()->firstOrFail();
            $promotion->actual_amount = Money::of((string) $promotion->actual_amount)->add($amount)->amount();
            $promotion->save();
        });
    }

    private function expense(Expense $document, User $actor): void
    {
        DB::transaction(function () use ($document, $actor): void {
            $document->load('partner', 'financialAccount.chartOfAccount');
            $amount = Money::of((string) $document->amount)->amount();
            $description = 'Expense '.$document->reference.' · '.$document->category->label();
            $lines = [[
                'account_code' => $document->category->accountCode(),
                'partner_id' => $document->partner_id,
                'debit' => $amount,
                'credit' => '0.00',
                'description' => $description,
            ]];

            if ($document->partner_id !== null) {
                $lines[] = [
                    'account_code' => ChartAccountCode::PartnerCapital,
                    'partner_id' => $document->partner_id,
                    'debit' => '0.00',
                    'credit' => $amount,
                    'description' => $description,
                ];
            } else {
                $account = $document->financialAccount;
                if ($account === null) {
                    throw new ApprovalStateException('A business expense needs a financial account.');
                }

                $lines[] = [
                    'account_code' => $account->chartOfAccount->code,
                    'financial_account_id' => $account->id,
                    'debit' => '0.00',
                    'credit' => $amount,
                    'description' => $description,
                ];
            }

            $entry = $this->journal->post($document, $actor, $document->transaction_date->toDateString(), $description, $lines);
            $document->journal_entry_id = $entry->id;
            $document->save();
        });
    }
}
