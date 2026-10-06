<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        RateLimiter::for('login', static function (Request $request): Limit {
            $rawEmail = $request->input('email');
            $normalizedEmail = mb_strtolower(trim(is_string($rawEmail) ? $rawEmail : ''));
            $ip = (string) $request->ip();
            $key = hash('sha256', $normalizedEmail.'|'.$ip);

            return Limit::perMinute(5)->by($key);
        });

        $this->registerCatalogueGates();
    }

    /**
     * Executable ability matrix: every catalogue permission maps to a gate
     * that requires a verified, active actor carrying that permission. Gates
     * decide objectless abilities (lists); object-level protections (self,
     * last admin, immutable admin role) stay in the policies, which the
     * mutation layer must always consult in addition to the gate.
     */
    private function registerCatalogueGates(): void
    {
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, static fn (User $actor): bool => $actor->email_verified_at !== null
                && $actor->deactivated_at === null
                && $actor->hasPermissionTo($permission->value));
        }
    }
}
