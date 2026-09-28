<?php

declare(strict_types=1);

namespace App\Actions\Partners;

use App\Enums\AuditAction;
use App\Models\Partner;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

final class UnlinkPartnerUser
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function execute(Partner $partner): Partner
    {
        return DB::transaction(function () use ($partner): Partner {
            $before = $partner->auditSnapshot();
            $partner->user()->dissociate();
            $partner->save();

            $this->audit->record(
                AuditAction::Updated,
                $partner,
                $before,
                $partner->fresh()->auditSnapshot(),
            );

            return $partner->fresh() ?? $partner;
        });
    }
}
