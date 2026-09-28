<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class Format
{
    public static function date(mixed $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        $format = (string) config('mpstore.date_format', 'd-M-Y');

        if ($date instanceof CarbonInterface) {
            return $date->format($format);
        }

        return Carbon::parse((string) $date)->format($format);
    }

    public static function dateTime(mixed $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        $carbon = $date instanceof CarbonInterface
            ? $date->copy()
            : Carbon::parse((string) $date);

        return $carbon
            ->timezone((string) config('app.timezone'))
            ->format('d-M-Y H:i');
    }

    public static function percent(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return number_format((float) $value, 2).'%';
    }
}
