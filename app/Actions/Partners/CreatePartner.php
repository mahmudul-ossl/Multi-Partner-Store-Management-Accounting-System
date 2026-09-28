<?php

declare(strict_types=1);

namespace App\Actions\Partners;

use App\Enums\AuditAction;
use App\Enums\PartnerStatus;
use App\Models\Partner;
use App\Services\AuditLogService;
use App\Services\PartnerCodeGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class CreatePartner
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly PartnerCodeGenerator $codes,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes): Partner
    {
        return DB::transaction(function () use ($attributes): Partner {
            $partner = $this->persist($attributes);

            $this->audit->record(
                AuditAction::Created,
                $partner,
                null,
                $partner->auditSnapshot(),
            );

            return $partner;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function persist(array $attributes): Partner
    {
        $code = isset($attributes['partner_code']) && is_string($attributes['partner_code']) && $attributes['partner_code'] !== ''
            ? $attributes['partner_code']
            : $this->codes->next();

        try {
            return Partner::query()->create([
                'partner_code' => $code,
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
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception) && empty($attributes['partner_code'])) {
                $attributes['partner_code'] = $this->codes->next();

                return Partner::query()->create([
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
            }

            throw $exception;
        }
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();

        return in_array($code, ['23000', '23505'], true);
    }
}
