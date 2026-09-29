<?php

declare(strict_types=1);

namespace App\Services\Approvals;

use App\Enums\ApprovalRequestType;
use App\Models\ApprovalThreshold;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

final class ApprovalThresholdService
{
    /**
     * @param  array{request_type: string, min_amount: string|int, max_amount?: string|int|null, required_approvals: int}  $attributes
     */
    public function save(array $attributes, ?ApprovalThreshold $threshold = null): ApprovalThreshold
    {
        $type = ApprovalRequestType::from($attributes['request_type']);
        $min = Money::of($attributes['min_amount'])->amount();
        $max = ($attributes['max_amount'] ?? null) === null || $attributes['max_amount'] === ''
            ? null
            : Money::of($attributes['max_amount'])->amount();

        if ($max !== null && Money::of($max)->compare($min) === -1) {
            throw ValidationException::withMessages([
                'max_amount' => 'The maximum must be at least the minimum.',
            ]);
        }

        if ($this->overlaps($type, $min, $max, $threshold?->id)) {
            throw ValidationException::withMessages([
                'min_amount' => 'This band overlaps another band for the same request type.',
            ]);
        }

        $required = (int) $attributes['required_approvals'];

        if ($required < 1) {
            throw ValidationException::withMessages([
                'required_approvals' => 'At least one approval is required.',
            ]);
        }

        $threshold ??= new ApprovalThreshold;
        $threshold->fill([
            'request_type' => $type,
            'min_amount' => $min,
            'max_amount' => $max,
            'required_approvals' => $required,
        ]);
        $threshold->save();

        return $threshold;
    }

    public function delete(ApprovalThreshold $threshold): void
    {
        $remaining = ApprovalThreshold::query()
            ->where('request_type', $threshold->request_type)
            ->whereKeyNot($threshold->id)
            ->count();

        if ($remaining === 0) {
            throw ValidationException::withMessages([
                'threshold' => 'Keep at least one band for this request type.',
            ]);
        }

        $threshold->delete();
    }

    private function overlaps(ApprovalRequestType $type, string $min, ?string $max, ?int $ignoreId): bool
    {
        $upper = $max ?? '9999999999999999.99';

        return ApprovalThreshold::query()
            ->where('request_type', $type)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('min_amount', '<=', $upper)
            ->where(function ($query) use ($min): void {
                $query->whereNull('max_amount')->orWhere('max_amount', '>=', $min);
            })
            ->exists();
    }
}
