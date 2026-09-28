<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoleName;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class PartnerDirectory
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(User $actor, array $filters): LengthAwarePaginator
    {
        $sort = $this->sortColumn((string) ($filters['sort'] ?? 'name'));
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $this->visibleTo($actor)
            ->with('user:id,name,email')
            ->when($this->search($filters['search'] ?? null), function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('partner_code', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, function (Builder $query, mixed $status): void {
                $query->where('status', $status);
            })
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @return Builder<Partner>
     */
    public function visibleTo(User $actor): Builder
    {
        $query = Partner::query();

        if ($this->restrictsToOwnRecord($actor)) {
            $query->where('user_id', $actor->id);
        }

        return $query;
    }

    public function restrictsToOwnRecord(User $actor): bool
    {
        if (! $actor->hasRole(RoleName::Partner->value)) {
            return false;
        }

        return ! $actor->hasAnyRole([
            RoleName::SuperAdmin->value,
            RoleName::Admin->value,
            RoleName::Accountant->value,
            RoleName::InventoryManager->value,
            RoleName::SalesManager->value,
            RoleName::Viewer->value,
        ]);
    }

    private function sortColumn(string $sort): string
    {
        return in_array($sort, [
            'name',
            'partner_code',
            'joining_date',
            'ownership_percentage',
            'investment_percentage',
            'status',
        ], true) ? $sort : 'name';
    }

    private function search(mixed $search): ?string
    {
        if (! is_string($search)) {
            return null;
        }

        $search = trim(str_replace(['%', '_'], '', $search));

        return $search === '' ? null : $search;
    }
}
