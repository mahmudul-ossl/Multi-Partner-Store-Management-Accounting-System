<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class AuditLogDirectory
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with('user:id,name,email')
            ->when($filters['action'] ?? null, function (Builder $query, mixed $action): void {
                $query->where('action', $action);
            })
            ->when($this->search($filters['search'] ?? null), function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('action', 'like', "%{$search}%")
                        ->orWhere('model_type', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhereHas('user', function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
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
