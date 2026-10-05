<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * FEAT-20 negative guards (F1/F2/F5), verified against a SIMULATED
 * production environment inside the test container only — no real
 * production deployment is involved:
 * - production must NOT register the dev-only infra:probe schedule entry;
 * - invoking infra:probe outside local/testing must be a no-op SUCCESS
 *   with no Redis connection (proven by pointing it at a closed port) and
 *   no keys created;
 * - invoking infra:smoke-mail / infra:fail-job outside local/testing must
 *   be a no-op SUCCESS: no mailable sent (sync+SMTP would land in the
 *   isolated Mailpit if the guard leaked) and no job dispatched;
 * - queue.connections.redis.after_commit must default to FALSE when
 *   QUEUE_AFTER_COMMIT is absent.
 */
final class InfraProdEnvGuardTest extends TestCase
{
    /**
     * @param  array<string, string|false>  $overrides
     * @return array{0: Process, 1: array<string, string|false>}
     */
    private function runArtisanInEnv(array $overrides, string $command): array
    {
        $env = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_KEY' => $this->stringConfig('app.key', ''),
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => $this->stringConfig('database.connections.pgsql.host', 'postgres-test'),
            'DB_PORT' => '5432',
            'DB_DATABASE' => $this->stringConfig('database.connections.pgsql.database', 'starter_api_test'),
            'DB_USERNAME' => $this->stringConfig('database.connections.pgsql.username', 'starter_api_test'),
            'DB_PASSWORD' => $this->stringConfig('database.connections.pgsql.password', ''),
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'log',
            'LOG_CHANNEL' => 'stderr',
            'LOG_LEVEL' => 'warning',
            // Drop inherited vars explicitly: false removes them from the
            // child process environment.
            'QUEUE_AFTER_COMMIT' => false,
            'REDIS_QUEUE' => false,
            ...$overrides,
        ];

        $process = new Process(
            [PHP_BINARY, base_path('artisan'), ...explode(' ', $command)],
            base_path(),
            $env,
            null,
            60.0,
        );
        $process->run();

        return [$process, $env];
    }

    public function test_production_does_not_register_the_probe_schedule(): void
    {
        [$process] = $this->runArtisanInEnv([], 'schedule:list');

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertStringNotContainsString('infra:probe', $process->getOutput());
    }

    public function test_probe_invocation_outside_local_testing_is_success_without_redis(): void
    {
        // Redis pointed at a closed port: the guard must exit SUCCESS
        // without ever attempting a connection.
        [$process] = $this->runArtisanInEnv([
            'REDIS_HOST' => '127.0.0.1',
            'REDIS_PORT' => '6390',
        ], 'infra:probe');

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('desativada fora de local/testing', $process->getOutput());

        // No keys may have been created on the real test Redis either.
        $this->assertSame(0, (int) Redis::connection()->exists('infra:probe:last-guards'));
        $this->assertSame([], (array) Redis::connection()->keys('healthcheck:*'));
    }

    public function test_smoke_mail_invocation_outside_local_testing_sends_nothing(): void
    {
        $apiUrl = rtrim($this->stringConfig('mail.infra_smoke.mailpit_api_url', 'http://mailpit-test:8025'), '/');
        $before = $this->mailpitTotal($apiUrl);

        // SMTP + sync queue in the simulated production child: a leaked
        // dispatch would execute inline and deliver into the isolated
        // Mailpit. The guard must keep the inbox untouched and exit SUCCESS.
        [$process] = $this->runArtisanInEnv([
            'MAIL_MAILER' => 'smtp',
            'MAIL_HOST' => 'mailpit-test',
            'MAIL_PORT' => '1025',
            'MAIL_FROM_ADDRESS' => 'no-reply@example.test',
        ], 'infra:smoke-mail');

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('desativado fora de local/testing', $process->getOutput());
        $this->assertSame($before, $this->mailpitTotal($apiUrl));
    }

    public function test_fail_job_invocation_outside_local_testing_dispatches_nothing(): void
    {
        if (! Schema::hasTable('failed_jobs')) {
            Artisan::call('migrate', ['--force' => true]);
        }
        $before = (int) DB::table('failed_jobs')->count();

        // sync queue: a leaked dispatch would execute the always-failing job
        // inline and exit non-zero. The guard must exit SUCCESS, print the
        // disabled marker and leave failed_jobs untouched.
        [$process] = $this->runArtisanInEnv([
            'REDIS_HOST' => '127.0.0.1',
            'REDIS_PORT' => '6390',
        ], 'infra:fail-job');

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('desativado fora de local/testing', $process->getOutput());
        $this->assertSame($before, (int) DB::table('failed_jobs')->count());
    }

    public function test_after_commit_defaults_to_false_without_the_env_var(): void
    {
        [$process] = $this->runArtisanInEnv([
            'REDIS_HOST' => '127.0.0.1',
            'REDIS_PORT' => '6390',
        ], 'config:show queue.connections.redis');

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        $output = $process->getOutput();
        $this->assertMatchesRegularExpression('/after_commit[^\n]*false/', $output, $output);
    }

    private function stringConfig(string $key, string $default): string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function mailpitTotal(string $apiUrl): int
    {
        $response = Http::timeout(5)->get($apiUrl.'/api/v1/messages');
        $this->assertTrue($response->successful(), sprintf('Mailpit API unreachable at %s', $apiUrl));

        $payload = $response->json();
        $total = is_array($payload) && isset($payload['total']) && is_int($payload['total']) ? $payload['total'] : 0;

        return $total;
    }
}
