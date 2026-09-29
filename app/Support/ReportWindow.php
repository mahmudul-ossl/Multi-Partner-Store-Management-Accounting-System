<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ApprovalStateException;
use Carbon\CarbonImmutable;

/**
 * Profit-and-loss windows. Weeks start on Monday.
 */
final class ReportWindow
{
    /**
     * @return array{0: string, 1: string}
     */
    public static function resolve(string $period, ?string $from = null, ?string $to = null): array
    {
        $today = CarbonImmutable::now()->startOfDay();

        return match ($period) {
            'today' => [$today->toDateString(), $today->toDateString()],
            'this_week' => [
                $today->startOfWeek(CarbonImmutable::MONDAY)->toDateString(),
                $today->endOfWeek(CarbonImmutable::SUNDAY)->toDateString(),
            ],
            'this_month' => [
                $today->startOfMonth()->toDateString(),
                $today->endOfMonth()->toDateString(),
            ],
            'previous_month' => [
                $today->subMonthNoOverflow()->startOfMonth()->toDateString(),
                $today->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'custom' => self::custom($from, $to),
            default => throw new ApprovalStateException('Choose a profit and loss period.'),
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function custom(?string $from, ?string $to): array
    {
        if ($from === null || $from === '' || $to === null || $to === '') {
            throw new ApprovalStateException('A custom period needs a start and an end date.');
        }

        if ($from > $to) {
            throw new ApprovalStateException('The start date cannot be after the end date.');
        }

        return [$from, $to];
    }
}
