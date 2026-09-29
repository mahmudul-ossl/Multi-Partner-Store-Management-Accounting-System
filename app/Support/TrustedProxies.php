<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Middleware\TrustProxies;

/**
 * Applies TRUSTED_PROXIES. An empty value trusts nobody, so production does
 * not accept forged X-Forwarded-* headers. "*" trusts the immediate peer,
 * which is what a Cloudflare tunnel needs. A comma-separated list trusts
 * only those addresses.
 */
final class TrustedProxies
{
    public static function apply(): void
    {
        $proxies = config('mpstore.trusted_proxies');

        if (! is_string($proxies)) {
            return;
        }

        $proxies = trim($proxies);

        if ($proxies === '') {
            return;
        }

        TrustProxies::at($proxies);
    }
}
