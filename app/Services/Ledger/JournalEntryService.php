<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Exceptions\ApprovalStateException;
use App\Models\ChartOfAccount;
use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\Money;
use App\Support\Sequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class JournalEntryService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @param  list<array{
     *     account_code: string,
     *     partner_id?: int|null,
     *     financial_account_id?: int|null,
     *     debit: string|int,
     *     credit: string|int,
     *     description?: string|null
     * }>  $lines
     */
    public function post(Model $source, User $actor, string $date, string $description, array $lines): JournalEntry
    {
        return DB::transaction(function () use ($source, $actor, $date, $description, $lines): JournalEntry {
            $normalized = $this->normalize($lines);
            BalancedEntry::assertBalanced($normalized);

            $entry = JournalEntry::query()->create([
                'entry_date' => $date,
                'reference' => Sequence::next(JournalEntry::class, 'reference', 'JE'),
                'source_type' => $source->getMorphClass(),
                'source_id' => $source->getKey(),
                'description' => $description,
                'status' => DocumentStatus::Completed,
                'created_by' => $actor->id,
            ]);

            foreach ($normalized as $line) {
                $entry->lines()->create([
                    'chart_of_account_id' => $line['chart_of_account_id'],
                    'partner_id' => $line['partner_id'],
                    'supplier_id' => $line['supplier_id'],
                    'financial_account_id' => $line['financial_account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'description' => $line['description'] ?? $description,
                ]);

                $this->moveFinancialAccount($line);
            }

            $this->audit->record(AuditAction::Created, $entry, null, [
                'reference' => $entry->reference,
                'description' => $entry->description,
                'status' => $entry->status->value,
            ], $actor);

            return $entry->load('lines');
        });
    }

    public function reverse(JournalEntry $entry, User $actor, string $description): JournalEntry
    {
        return DB::transaction(function () use ($entry, $actor, $description): JournalEntry {
            $entry = JournalEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if ($entry->status !== DocumentStatus::Completed) {
                throw new ApprovalStateException('Only a posted journal entry can be reversed.');
            }

            if ($entry->reversals()->exists()) {
                throw new ApprovalStateException('This journal entry has already been reversed.');
            }

            $entry->load('lines.account');

            $lines = $entry->lines->map(fn ($line): array => [
                'account_code' => $line->account->code,
                'partner_id' => $line->partner_id,
                'supplier_id' => $line->supplier_id,
                'financial_account_id' => $line->financial_account_id,
                'debit' => (string) $line->credit,
                'credit' => (string) $line->debit,
                'description' => $description,
            ])->all();

            $source = $entry->source;

            if (! $source instanceof Model) {
                throw new ApprovalStateException('The journal entry has no source document.');
            }

            $reversal = $this->post(
                $source,
                $actor,
                now()->toDateString(),
                $description,
                $lines,
            );

            $reversal->reversal_of = $entry->id;
            $reversal->save();

            $before = $entry->status->value;
            $entry->status = DocumentStatus::Reversed;
            $entry->save();

            $this->audit->record(AuditAction::Reversed, $entry, [
                'status' => $before,
            ], [
                'status' => $entry->status->value,
                'reversal_reference' => $reversal->reference,
            ], $actor);

            return $reversal->fresh('lines');
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{chart_of_account_id: int, partner_id: int|null, supplier_id: int|null, financial_account_id: int|null, debit: string, credit: string, description: string|null}>
     */
    private function normalize(array $lines): array
    {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('A journal entry needs at least two lines.');
        }

        $normalized = [];

        foreach ($lines as $line) {
            $debit = Money::of($line['debit'])->amount();
            $credit = Money::of($line['credit'])->amount();

            if (bccomp($debit, '0.00', 2) === 0 && bccomp($credit, '0.00', 2) === 0) {
                throw new InvalidArgumentException('A journal line needs a debit or a credit.');
            }

            $account = ChartOfAccount::query()->where('code', $line['account_code'])->first();

            if (! $account instanceof ChartOfAccount || ! $account->is_active) {
                throw new InvalidArgumentException('Ledger account '.$line['account_code'].' is not available.');
            }

            $financialId = $line['financial_account_id'] ?? null;
            $financialId = $financialId !== null ? (int) $financialId : null;

            if ($financialId !== null) {
                $financial = FinancialAccount::query()->find($financialId);

                if (! $financial instanceof FinancialAccount || ! $financial->is_active) {
                    throw new InvalidArgumentException('The financial account is not available.');
                }

                if ((int) $financial->chart_of_account_id !== (int) $account->id) {
                    throw new InvalidArgumentException('The financial account is not linked to that ledger account.');
                }
            }

            $normalized[] = [
                'chart_of_account_id' => $account->id,
                'partner_id' => isset($line['partner_id']) ? (int) $line['partner_id'] : null,
                'supplier_id' => isset($line['supplier_id']) ? (int) $line['supplier_id'] : null,
                'financial_account_id' => $financialId,
                'debit' => $debit,
                'credit' => $credit,
                'description' => $line['description'] ?? null,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array{financial_account_id: int|null, debit: string, credit: string}  $line
     */
    private function moveFinancialAccount(array $line): void
    {
        if ($line['financial_account_id'] === null) {
            return;
        }

        $account = FinancialAccount::query()
            ->whereKey($line['financial_account_id'])
            ->lockForUpdate()
            ->firstOrFail();

        $account->applyMovement($line['debit'], $line['credit']);
    }
}
