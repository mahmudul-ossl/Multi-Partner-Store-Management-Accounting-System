<?php

declare(strict_types=1);

namespace App\Services\Approvals;

use App\Enums\ApprovalActionType;
use App\Enums\ApprovalRequestType;
use App\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ApprovalDirectory
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function actionable(User $actor, array $filters): LengthAwarePaginator
    {
        $sort = ($filters['sort'] ?? 'requested_at') === 'amount' ? 'amount' : 'requested_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $this->actionableQuery($actor)
            ->with(['requester:id,name', 'reference'])
            ->when($filters['type'] ?? null, fn (Builder $query, mixed $type) => $query->where('request_type', $type))
            ->when(trim((string) ($filters['search'] ?? '')) !== '', function (Builder $query) use ($filters): void {
                $term = trim((string) $filters['search']);
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('note', 'like', "%{$term}%")
                        ->orWhereHas('requester', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"));
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();
    }

    public function countActionable(User $actor): int
    {
        return $this->actionableQuery($actor)->count();
    }

    /**
     * @return Builder<ApprovalRequest>
     */
    public function actionableQuery(User $actor): Builder
    {
        $types = array_values(array_map(
            fn (ApprovalRequestType $type): string => $type->value,
            array_filter(
                ApprovalRequestType::cases(),
                fn (ApprovalRequestType $type): bool => $actor->can($type->approvePermission()->value),
            ),
        ));

        return ApprovalRequest::query()
            ->whereIn('status', [ApprovalStatus::Pending->value, ApprovalStatus::PartiallyApproved->value])
            ->whereIn('request_type', $types)
            ->where('requested_by', '!=', $actor->id)
            ->whereDoesntHave('actions', function (Builder $query) use ($actor): void {
                $query->where('user_id', $actor->id)->where('action', ApprovalActionType::Approved);
            });
    }
}
