<?php

declare(strict_types=1);

namespace App\Actions\Partners;

use App\Enums\AuditAction;
use App\Models\Partner;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LinkPartnerUser
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function execute(Partner $partner, int $userId): Partner
    {
        return DB::transaction(function () use ($partner, $userId): Partner {
            $user = User::query()->findOrFail($userId);

            $alreadyLinked = Partner::query()
                ->where('user_id', $user->id)
                ->whereKeyNot($partner->id)
                ->exists();

            if ($alreadyLinked) {
                throw ValidationException::withMessages([
                    'user_id' => 'This user is already linked to another partner.',
                ]);
            }

            $before = $partner->auditSnapshot();
            $partner->user()->associate($user);
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
