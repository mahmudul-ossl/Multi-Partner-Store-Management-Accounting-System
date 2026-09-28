<?php

declare(strict_types=1);

namespace App\Actions\Partners;

use App\Enums\AuditAction;
use App\Enums\PartnerStatus;
use App\Models\Partner;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

final class UpdatePartner
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Partner $partner, array $attributes): Partner
    {
        return DB::transaction(function () use ($partner, $attributes): Partner {
            $before = $partner->auditSnapshot();

            $partner->fill([
                'partner_code' => $attributes['partner_code'],
                'name' => $attributes['name'],
                'phone' => $attributes['phone'] ?? null,
                'email' => $attributes['email'] ?? null,
                'address' => $attributes['address'] ?? null,
                'joining_date' => $attributes['joining_date'],
                'ownership_percentage' => $attributes['ownership_percentage'],
                'investment_percentage' => $attributes['investment_percentage'],
                'status' => PartnerStatus::from($attributes['status']),
                'user_id' => $attributes['user_id'] ?? null,
                'notes' => $attributes['notes'] ?? null,
            ]);

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
