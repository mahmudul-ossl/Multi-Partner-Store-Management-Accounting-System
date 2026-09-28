<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Events\Login;

final class RecordLoginAudit
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $this->audit->record(AuditAction::Login, $user, null, [
            'email' => $user->email,
        ], $user);
    }
}
