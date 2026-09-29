<?php

declare(strict_types=1);

namespace App\Providers;

use App\Policies\RolePolicy;
use App\Support\TrustedProxies;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        TrustedProxies::apply();

        Password::defaults(function (): Password {
            return Password::min(10)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols();
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $email = strtolower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        Gate::policy(Role::class, RolePolicy::class);
    }
}
