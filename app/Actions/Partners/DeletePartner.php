<?php

declare(strict_types=1);

namespace App\Actions\Partners;

use App\Enums\AuditAction;
use App\Models\Partner;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

final class DeletePartner
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function execute(Partner $partner): void
    {
        DB::transaction(function () use ($partner): void {
            $before = $partner->auditSnapshot();
            $partner->delete();

            $this->audit->record(AuditAction::Deleted, $partner, $before, null);
        });
    }
}
