<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class UserDirectory
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? '', ['name', 'email', 'created_at'], true)
            ? (string) $filters['sort']
            : 'name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return User::query()
            ->with('roles:id,name')
            ->when($this->search($filters['search'] ?? null), function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when(
                is_string($filters['role'] ?? null) && RoleName::tryFrom($filters['role']) instanceof RoleName,
                function (Builder $query) use ($filters): void {
                    $query->role((string) $filters['role']);
                },
            )
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== '' && $filters['is_active'] !== null, function (Builder $query) use ($filters): void {
                $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();
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
