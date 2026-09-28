<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\User;
use App\Notifications\AccountCreated;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

final class CreateUser
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null, password: string, role: string, is_active: bool}  $attributes
     */
    public function execute(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            $user = User::query()->create([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'phone' => $attributes['phone'] ?? null,
                'password' => $attributes['password'],
                'is_active' => (bool) $attributes['is_active'],
                'email_verified_at' => now(),
            ]);

            $user->syncRoles([RoleName::from($attributes['role'])->value]);

            $this->audit->record(
                AuditAction::Created,
                $user,
                null,
                $user->fresh()->auditSnapshot(),
            );

            DB::afterCommit(function () use ($user): void {
                $user->notify(new AccountCreated);
            });

            return $user->fresh() ?? $user;
        });
    }
}
