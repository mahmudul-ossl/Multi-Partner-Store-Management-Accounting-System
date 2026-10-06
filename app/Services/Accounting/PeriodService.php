<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\AuditAction;
use App\Exceptions\ApprovalStateException;
use App\Models\AccountingPeriod;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\Format;

final class PeriodService
{
    public function __construct(
        private readonly PeriodGuard $guard,
        private readonly AuditLogService $audit,
    ) {}

    public function close(User $actor, string $date, ?string $note = null): AccountingPeriod
    {
        $closed = $this->guard->closedThrough();

        if ($closed !== null && $date <= $closed) {
            throw new ApprovalStateException('The books are already closed through '.Format::date($closed).'.');
        }

        $period = AccountingPeriod::query()->create([
            'closed_through' => $date,
            'note' => $note,
            'closed_by' => $actor->id,
        ]);

        $this->audit->record(AuditAction::Created, $period, null, [
            'closed_through' => $date,
        ], $actor);

        return $period;
    }
}
