<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        RateLimiter::for('api', static function (Request $request): Limit {
            $identifier = $request->user()?->getAuthIdentifier();

            return Limit::perMinute(60)->by(
                is_int($identifier) || is_string($identifier) ? (string) $identifier : (string) $request->ip(),
            );
        });
    }
}
