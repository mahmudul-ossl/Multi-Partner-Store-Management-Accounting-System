<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\ApprovalRequestType;
use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Exceptions\ApprovalStateException;
use App\Exceptions\ImmutableDocumentException;
use App\Models\ChartOfAccount;
use App\Models\FinancialAccount;
use App\Models\ManualJournal;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\AuditLogService;
use App\Services\Ledger\BalancedEntry;
use App\Support\Money;
use App\Support\Sequence;
use Illuminate\Support\Facades\DB;

final class ManualJournalService
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array{entry_date: string, description: string, lines: list<array<string, mixed>>}  $attributes
     */
    public function create(User $actor, array $attributes): ManualJournal
    {
        return DB::transaction(function () use ($actor, $attributes): ManualJournal {
            $lines = $this->normalizedLines($attributes['lines']);
            $amount = $this->totalDebit($lines);

            $journal = ManualJournal::query()->create([
                'entry_date' => $attributes['entry_date'],
                'reference' => Sequence::next(ManualJournal::class, 'reference', 'MJ'),
                'description' => $attributes['description'],
                'amount' => $amount,
                'status' => DocumentStatus::Pending,
                'created_by' => $actor->id,
            ]);

            $this->storeLines($journal, $lines);

            $this->approvals->submit(
                $journal,
                ApprovalRequestType::ManualJournal,
                $amount,
                $actor,
                $journal->description,
            );

            $this->audit->record(AuditAction::Created, $journal, null, [
                'reference' => $journal->reference,
                'amount' => $amount,
                'status' => $journal->status->value,
            ], $actor);

            return $journal->load('lines', 'approvalRequest');
        });
    }

    /**
     * @param  array{entry_date: string, description: string, lines: list<array<string, mixed>>}  $attributes
     */
    public function update(ManualJournal $journal, User $actor, array $attributes): ManualJournal
    {
        return DB::transaction(function () use ($journal, $actor, $attributes): ManualJournal {
            $journal = ManualJournal::query()->whereKey($journal->id)->lockForUpdate()->firstOrFail();

            if (! $journal->isEditable()) {
                throw new ImmutableDocumentException('Approved records cannot be edited.');
            }

            $lines = $this->normalizedLines($attributes['lines']);
            $amount = $this->totalDebit($lines);
            $before = ['amount' => (string) $journal->amount, 'description' => $journal->description];

            $journal->fill([
                'entry_date' => $attributes['entry_date'],
                'description' => $attributes['description'],
                'amount' => $amount,
            ]);
            $journal->save();
            $journal->lines()->delete();
            $this->storeLines($journal, $lines);

            $this->approvals->syncAmount($journal, ApprovalRequestType::ManualJournal, $amount);
            $this->audit->record(AuditAction::Updated, $journal, $before, [
                'amount' => $amount,
                'description' => $journal->description,
            ], $actor);

            return $journal->load('lines', 'approvalRequest');
        });
    }

    public function cancel(ManualJournal $journal, User $actor, ?string $comment = null): ManualJournal
    {
        $request = $journal->approvalRequest()->firstOrFail();
        $this->approvals->cancel($request, $actor, $comment);

        return $journal->fresh();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{chart_of_account_id: int, partner_id: int|null, financial_account_id: int|null, debit: string, credit: string, description: string|null}>
     */
    private function normalizedLines(array $lines): array
    {
        if (count($lines) < 2) {
            throw new ApprovalStateException('A journal entry needs at least two lines.');
        }

        $prepared = [];

        foreach ($lines as $line) {
            $account = ChartOfAccount::query()->where('code', $line['account_code'])->first();

            if (! $account instanceof ChartOfAccount || ! $account->is_active) {
                throw new ApprovalStateException('Ledger account '.($line['account_code'] ?? '').' is not available.');
            }

            $debit = Money::of($line['debit'] ?? '0')->amount();
            $credit = Money::of($line['credit'] ?? '0')->amount();
            $financialId = $this->nullableInt($line['financial_account_id'] ?? null);
            $hasFinancials = $account->financialAccounts()->where('is_active', true)->exists();

            if ($hasFinancials && $financialId === null) {
                throw new ApprovalStateException('Choose the cash, bank, or wallet account for '.$account->code.'.');
            }

            if (! $hasFinancials && $financialId !== null) {
                throw new ApprovalStateException('Ledger account '.$account->code.' has no financial account.');
            }

            if ($financialId !== null) {
                $financial = FinancialAccount::query()->find($financialId);

                if (! $financial instanceof FinancialAccount || ! $financial->is_active || (int) $financial->chart_of_account_id !== (int) $account->id) {
                    throw new ApprovalStateException('The financial account is not linked to '.$account->code.'.');
                }
            }

            $prepared[] = [
                'chart_of_account_id' => $account->id,
                'partner_id' => $this->nullableInt($line['partner_id'] ?? null),
                'financial_account_id' => $financialId,
                'debit' => $debit,
                'credit' => $credit,
                'description' => $line['description'] ?? null,
            ];
        }

        BalancedEntry::assertBalanced($prepared);

        return $prepared;
    }

    /**
     * @param  list<array{debit: string}>  $lines
     */
    private function totalDebit(array $lines): string
    {
        $total = '0.00';

        foreach ($lines as $line) {
            $total = Money::of($total)->add($line['debit'])->amount();
        }

        if (Money::of($total)->compare('0.00') !== 1) {
            throw new ApprovalStateException('The journal amount must be greater than zero.');
        }

        return $total;
    }

    /**
     * @param  list<array{chart_of_account_id: int, partner_id: int|null, financial_account_id: int|null, debit: string, credit: string, description: string|null}>  $lines
     */
    private function storeLines(ManualJournal $journal, array $lines): void
    {
        foreach ($lines as $line) {
            $journal->lines()->create($line);
        }
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
