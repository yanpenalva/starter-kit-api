<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Redis;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * FEAT-20 negative guards (F1/F2), verified against a SIMULATED
 * production environment inside the test container only — no real
 * production deployment is involved:
 * - production must NOT register the dev-only infra:probe schedule entry;
 * - invoking infra:probe outside local/testing must be a no-op SUCCESS
 *   with no Redis connection (proven by pointing it at a closed port) and
 *   no keys created;
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
        $this->assertStringContainsString('disabled outside local/testing', $process->getOutput());

        // No keys may have been created on the real test Redis either.
        $this->assertSame(0, (int) Redis::connection()->exists('infra:probe:last-guards'));
        $this->assertSame([], (array) Redis::connection()->keys('healthcheck:*'));
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
}
