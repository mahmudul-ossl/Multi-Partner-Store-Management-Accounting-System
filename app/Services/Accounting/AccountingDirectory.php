<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountTransfer;
use App\Models\ChartOfAccount;
use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use App\Models\ManualJournal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class AccountingDirectory
{
    /**
     * @return Collection<int, ChartOfAccount>
     */
    public function chart(): Collection
    {
        return ChartOfAccount::query()->with('parent')->orderBy('code')->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function financialAccounts(array $filters): LengthAwarePaginator
    {
        return FinancialAccount::query()
            ->with('chartOfAccount')
            ->when(($filters['type'] ?? '') !== '', fn (Builder $query) => $query->where('type', $filters['type']))
            ->when(($filters['search'] ?? '') !== '', function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';
                $query->where('name', 'like', $term);
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function manualJournals(array $filters): LengthAwarePaginator
    {
        return ManualJournal::query()
            ->with('approvalRequest', 'author')
            ->when(($filters['status'] ?? '') !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(($filters['search'] ?? '') !== '', function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('reference', 'like', $term)->orWhere('description', 'like', $term);
                });
            })
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function transfers(array $filters): LengthAwarePaginator
    {
        return AccountTransfer::query()
            ->with('fromAccount', 'toAccount', 'approvalRequest', 'author')
            ->when(($filters['status'] ?? '') !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(($filters['search'] ?? '') !== '', function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';
                $query->where('reference', 'like', $term);
            })
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function entries(array $filters): LengthAwarePaginator
    {
        return JournalEntry::query()
            ->with('author', 'source')
            ->when(($filters['status'] ?? '') !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(($filters['search'] ?? '') !== '', function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('reference', 'like', $term)->orWhere('description', 'like', $term);
                });
            })
            ->when(($filters['from'] ?? '') !== '', fn (Builder $query) => $query->whereDate('entry_date', '>=', $filters['from']))
            ->when(($filters['to'] ?? '') !== '', fn (Builder $query) => $query->whereDate('entry_date', '<=', $filters['to']))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();
    }
}
