<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Permission;
use App\Providers\AppServiceProvider;
use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;
use Tests\TestCase;

/**
 * Focused coverage for wave 2B (FEAT-23-W2-B): the production guards in
 * App\Providers\AppServiceProvider::boot() were reachable only through artisan
 * sub-processes (InfraProdEnvGuardTest), whose execution pcov never records, so
 * those lines stayed uncovered.
 *
 * Every scenario boots the provider in-process with the test application flagged
 * as production; the flag and the touched config keys are always restored in
 * `finally`. No production application, .env file, mailer or session store is
 * involved.
 */
final class AppServiceProviderCoverageTest extends TestCase
{
    /**
     * Run $scenarios with the application flagged as production and $config applied.
     *
     * @param  array<string, mixed>  $config
     * @param  callable(): void  $scenarios
     */
    private function inProduction(array $config, callable $scenarios): void
    {
        $environment = $this->app['env'];
        $original = [];

        foreach (array_keys($config) as $key) {
            $original[$key] = config($key);
        }

        $this->app->instance('env', 'production');

        foreach ($config as $key => $value) {
            config()->set($key, $value);
        }

        try {
            $this->assertTrue($this->app->environment('production'), 'the production flag must be in effect');
            $scenarios();
        } finally {
            $this->app->instance('env', $environment);

            foreach ($original as $key => $value) {
                config()->set($key, $value);
            }
        }
    }

    /** Boot the provider and return the guard failure, or null when boot completed. */
    private function bootGuardFailure(): ?RuntimeException
    {
        try {
            (new AppServiceProvider($this->app))->boot();
        } catch (RuntimeException $failure) {
            return $failure;
        }

        return null;
    }

    public function test_boot_in_production_accepts_smtp_mailer_and_secure_cookie(): void
    {
        $this->inProduction(['mail.default' => 'smtp', 'session.secure' => true], function (): void {
            $this->assertNull($this->bootGuardFailure(), 'a valid production configuration must complete boot');

            $limiter = RateLimiter::limiter('api');
            $this->assertInstanceOf(Closure::class, $limiter, 'boot must leave the api limiter registered');

            $request = Request::create('/api/v1/health', 'GET');
            $limit = $limiter($request);

            $this->assertInstanceOf(Limit::class, $limit);
            $this->assertSame(60, $limit->maxAttempts, 'anonymous callers get 60 api requests per minute');
            $this->assertSame($request->ip(), $limit->key);

            $password = Password::defaults();
            $this->assertInstanceOf(Password::class, $password);
            $this->assertTrue(
                Validator::make(['password' => str_repeat('a', 11)], ['password' => $password])->fails(),
                'the registered password default must reject 11 characters',
            );
            $this->assertFalse(
                Validator::make(['password' => str_repeat('a', 12)], ['password' => $password])->fails(),
                'the registered password default must accept 12 characters',
            );

            $this->assertTrue(Gate::has(Permission::UsersView->value), 'boot must keep the catalogue gate defined');
        });
    }

    public function test_boot_in_production_rejects_log_null_and_missing_mailer(): void
    {
        $this->inProduction(['mail.default' => 'smtp', 'session.secure' => true], function (): void {
            foreach (['log', 'null', null] as $mailer) {
                config()->set('mail.default', $mailer);

                $failure = $this->bootGuardFailure();

                $this->assertInstanceOf(
                    RuntimeException::class,
                    $failure,
                    sprintf('mailer %s must be rejected in production', json_encode($mailer)),
                );
                $this->assertSame('MAIL_MAILER must not use log or null in production.', $failure->getMessage());
            }

            config()->set('mail.default', 'smtp');

            $this->assertNull($this->bootGuardFailure(), 'boot must recover once a real mailer is configured');
        });
    }

    public function test_boot_in_production_requires_secure_session_cookie(): void
    {
        $this->inProduction(['mail.default' => 'smtp', 'session.secure' => true], function (): void {
            foreach ([false, null] as $secure) {
                config()->set('session.secure', $secure);

                $failure = $this->bootGuardFailure();

                $this->assertInstanceOf(
                    RuntimeException::class,
                    $failure,
                    sprintf('session.secure=%s must be rejected in production', json_encode($secure)),
                );
                $this->assertSame('SESSION_SECURE_COOKIE=true is required in production.', $failure->getMessage());
            }

            config()->set('session.secure', true);

            $this->assertNull($this->bootGuardFailure());
        });
    }

    public function test_boot_outside_production_skips_the_mailer_and_cookie_guards(): void
    {
        $mailer = config('mail.default');
        $secureCookie = config('session.secure');

        config()->set('mail.default', 'log');
        config()->set('session.secure', false);

        try {
            $this->assertFalse($this->app->environment('production'));
            $this->assertNull($this->bootGuardFailure(), 'a logging mailer must not abort boot outside production');
            $this->assertSame('log', config('mail.default'), 'the guard must not rewrite the configured mailer');
            $this->assertFalse(config('session.secure'), 'the guard must not rewrite the session cookie flag');
        } finally {
            config()->set('mail.default', $mailer);
            config()->set('session.secure', $secureCookie);
        }
    }
}
