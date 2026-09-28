<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteUser
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly UpdateUser $updates,
    ) {}

    public function execute(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        DB::transaction(function () use ($user): void {
            $this->updates->guardLastSuperAdmin($user, null, null, true);

            $before = $user->auditSnapshot();
            $user->delete();

            $this->audit->record(AuditAction::Deleted, $user, $before, null);
        });
    }
}
