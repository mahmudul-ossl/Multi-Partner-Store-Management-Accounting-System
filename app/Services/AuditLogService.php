<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class AuditLogService
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(
        AuditAction $action,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $user = null,
    ): AuditLog {
        $actor = $user ?? auth()->user();

        return AuditLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'model_type' => $model?->getMorphClass(),
            'model_id' => $model?->getKey(),
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach (['password', 'remember_token', 'password_confirmation'] as $secret) {
            if (array_key_exists($secret, $values)) {
                $values[$secret] = '[redacted]';
            }
        }

        return $values;
    }
}
