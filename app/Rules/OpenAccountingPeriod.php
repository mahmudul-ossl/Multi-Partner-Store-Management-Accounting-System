<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\Accounting\PeriodGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a posting date on or before the latest period close.
 */
final class OpenAccountingPeriod implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return;
        }

        $closed = app(PeriodGuard::class)->closedThrough();
        $day = substr($value, 0, 10);

        if ($closed !== null && $day <= $closed) {
            $fail('That date is in a closed period.');
        }
    }
}
