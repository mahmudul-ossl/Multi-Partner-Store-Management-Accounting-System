<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard summaries are cached until a journal, stock movement, or approval changes.
 */
final class DashboardCache
{
    public const VERSION = 'dashboard.version';

    public static function bump(): void
    {
        DB::afterCommit(static function (): void {
            Cache::forever(self::VERSION, ((int) Cache::get(self::VERSION, 0)) + 1);
        });
    }
}
