<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateUser
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null, password?: string|null, role: string, is_active: bool}  $attributes
     */
    public function execute(User $user, array $attributes): User
    {
        return DB::transaction(function () use ($user, $attributes): User {
            $role = RoleName::from($attributes['role']);
            $this->guardLastSuperAdmin($user, $role, (bool) $attributes['is_active'], false);

            $before = $user->auditSnapshot();

            $user->fill([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'phone' => $attributes['phone'] ?? null,
                'is_active' => (bool) $attributes['is_active'],
            ]);

            if (! empty($attributes['password'])) {
                $user->password = $attributes['password'];
            }

            $user->save();
            $user->syncRoles([$role->value]);

            $after = $user->fresh()->auditSnapshot();

            if (! empty($attributes['password'])) {
                $before['password'] = '[redacted]';
                $after['password'] = '[redacted]';
            }

            $this->audit->record(AuditAction::Updated, $user, $before, $after);

            return $user->fresh() ?? $user;
        });
    }

    public function guardLastSuperAdmin(User $user, ?RoleName $nextRole, ?bool $nextActive, bool $deleting): void
    {
        if (! $user->hasRole(RoleName::SuperAdmin->value)) {
            return;
        }

        $remaining = User::query()->role(RoleName::SuperAdmin->value)->count();

        if ($remaining > 1) {
            return;
        }

        $demoting = $nextRole !== null && $nextRole !== RoleName::SuperAdmin;
        $deactivating = $nextActive === false;

        if ($deleting || $demoting || $deactivating) {
            throw ValidationException::withMessages([
                'role' => 'The last Super Admin cannot be removed, demoted, or deactivated.',
            ]);
        }
    }
}
