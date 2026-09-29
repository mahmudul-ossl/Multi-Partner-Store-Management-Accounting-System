<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\PartnerInvestment;
use App\Models\PartnerTransfer;
use App\Models\PartnerWithdrawal;
use App\Models\User;
use App\Services\PartnerDirectory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class FinanceDirectory
{
    public function __construct(private readonly PartnerDirectory $partners) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function investments(User $actor, array $filters): LengthAwarePaginator
    {
        $query = PartnerInvestment::query()->with(['partner:id,name,partner_code', 'financialAccount:id,name', 'approvalRequest']);
        $this->restrictPartner($actor, $query, 'partner_id');

        return $this->paginate($query, $filters, ['transaction_date', 'amount', 'reference', 'status']);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function withdrawals(User $actor, array $filters): LengthAwarePaginator
    {
        $query = PartnerWithdrawal::query()->with(['partner:id,name,partner_code', 'financialAccount:id,name', 'approvalRequest']);
        $this->restrictPartner($actor, $query, 'partner_id');

        return $this->paginate($query, $filters, ['transaction_date', 'amount', 'reference', 'status']);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function transfers(User $actor, array $filters): LengthAwarePaginator
    {
        $query = PartnerTransfer::query()->with(['fromPartner:id,name,partner_code', 'toPartner:id,name,partner_code', 'approvalRequest']);

        if ($this->partners->restrictsToOwnRecord($actor)) {
            $own = $actor->partner?->id;
            $query->where(function (Builder $query) use ($own): void {
                $query->where('from_partner_id', $own)->orWhere('to_partner_id', $own);
            });
        }

        return $this->paginate($query, $filters, ['transaction_date', 'amount', 'reference', 'status'], function (Builder $query, string $search): void {
            $query->where('reference', 'like', "%{$search}%")
                ->orWhereHas('fromPartner', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                ->orWhereHas('toPartner', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
        });
    }

    /**
     * @param  Builder<PartnerInvestment>|Builder<PartnerWithdrawal>  $query
     */
    private function restrictPartner(User $actor, Builder $query, string $column): void
    {
        if (! $this->partners->restrictsToOwnRecord($actor)) {
            return;
        }

        $query->where($column, $actor->partner?->id);
    }

    /**
     * @param  Builder<PartnerInvestment>|Builder<PartnerWithdrawal>|Builder<PartnerTransfer>  $query
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $sortable
     * @param  (callable(Builder, string): void)|null  $search
     */
    private function paginate(Builder $query, array $filters, array $sortable, ?callable $search = null): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? '', $sortable, true) ? (string) $filters['sort'] : 'transaction_date';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $term = trim((string) ($filters['search'] ?? ''));

        return $query
            ->when($term !== '', function (Builder $query) use ($term, $search): void {
                if ($search !== null) {
                    $query->where(fn (Builder $query) => $search($query, $term));

                    return;
                }

                $query->where(function (Builder $query) use ($term): void {
                    $query->where('reference', 'like', "%{$term}%")
                        ->orWhereHas('partner', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"));
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, mixed $status) => $query->where('status', $status))
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();
    }
}
