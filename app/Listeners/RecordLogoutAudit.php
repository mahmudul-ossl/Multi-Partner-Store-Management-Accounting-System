<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Events\Logout;

final class RecordLogoutAudit
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function handle(Logout $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $this->audit->record(AuditAction::Logout, $user, null, [
            'email' => $user->email,
        ], $user);
    }
}
