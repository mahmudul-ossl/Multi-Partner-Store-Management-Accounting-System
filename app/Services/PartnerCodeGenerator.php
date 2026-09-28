<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Partner;

final class PartnerCodeGenerator
{
    public function next(): string
    {
        $number = 0;

        foreach (Partner::withTrashed()->pluck('partner_code') as $code) {
            if (is_string($code) && preg_match('/^P-(\d+)$/', $code, $matches) === 1) {
                $number = max($number, (int) $matches[1]);
            }
        }

        return sprintf('P-%04d', $number + 1);
    }
}
