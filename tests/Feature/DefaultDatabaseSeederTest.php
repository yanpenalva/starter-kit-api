<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use ReflectionMethod;
use Symfony\Component\Process\Process;
use Tests\Concern\UsesTypedDatabaseConfig;
use Tests\TestCase;

/**
 * Proves the default seeding entrypoint (`migrate:fresh --seed`).
 *
 * `migrate:fresh` is destructive and Laravel schema commands operate on a
 * dedicated pooled/direct connection, so the command runs as a separate
 * process against the committed test database. These tests are therefore
 * exclusive: they must never run under a parallel test runner and they rely
 * on the dedicated `starter_api_test` database only.
 */
final class DefaultDatabaseSeederTest extends TestCase
{
    use UsesTypedDatabaseConfig;

    private ?string $sqliteDatabasePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->sqliteDatabasePath = tempnam(sys_get_temp_dir(), 'starter-api-seeder-');
            $this->assertIsString($this->sqliteDatabasePath);
            config()->set('database.connections.sqlite.database', $this->sqliteDatabasePath);
            DB::purge('sqlite');
        }

        $this->assertIsolatedSeedRuntime();
    }

    protected function tearDown(): void
    {
        if (is_string($this->sqliteDatabasePath)) {
            DB::purge('sqlite');
            @unlink($this->sqliteDatabasePath);
            @unlink($this->sqliteDatabasePath.'-journal');
        }

        parent::tearDown();
    }

    public function test_migrate_fresh_seed_command_succeeds_twice_and_keeps_the_canonical_catalogue(): void
    {
        $this->runMigrateFreshSeed();
        $this->runMigrateFreshSeed();

        $this->assertSame(count(Permission::cases()), DB::table('permissions')->where('guard_name', 'web')->count());
        $this->assertSame(2, DB::table('roles')->where('guard_name', 'web')->count());

        $permissionNames = DB::table('permissions')->orderBy('name')->pluck('name')->all();
        $expected = Permission::values();
        sort($expected);

        $this->assertSame($expected, array_values($permissionNames));

        $admin = DB::table('roles')->where('name', Role::Admin->value)->first();
        $this->assertNotNull($admin);
        $this->assertSame(
            count(Permission::cases()),
            (int) DB::table('role_has_permissions')->where('role_id', $admin->id)->count(),
        );

        $plain = DB::table('roles')->where('name', Role::User->value)->first();
        $this->assertNotNull($plain);
        $this->assertSame(
            0,
            (int) DB::table('role_has_permissions')->where('role_id', $plain->id)->count(),
        );
    }

    public function test_default_seeding_never_creates_accounts_or_role_assignments(): void
    {
        $this->runMigrateFreshSeed();

        $this->assertSame(0, DB::table('users')->count());
        $this->assertSame(0, DB::table('model_has_roles')->count());
    }

    public function test_guard_rejects_a_database_url_override(): void
    {

        $connection = $this->databaseConnectionName();
        config()->set("database.connections.{$connection}.url", 'postgres://synthetic:synthetic@127.0.0.1:5/none');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('database URL');

        try {
            $this->invokeSeedRuntimeGuard();
        } finally {
            config()->set("database.connections.{$connection}.url", null);
        }
    }

    public function test_guard_rejects_a_parallel_runner_token(): void
    {

        putenv('TEST_TOKEN=0');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('TEST_TOKEN');

        try {
            $this->invokeSeedRuntimeGuard();
        } finally {
            putenv('TEST_TOKEN');
        }
    }

    /**
     * Runs before anything destructive. It fails loudly when any of the
     * isolation preconditions is missing; error messages name only variable
     * and database identifiers, never values.
     */
    private function assertIsolatedSeedRuntime(): void
    {
        $this->assertTrue(
            app()->environment('testing'),
            'Seeder tests require the testing environment.',
        );

        $connection = $this->databaseConnectionName();
        $database = $this->databaseConfigString($this->databaseConnectionConfig($connection), 'database');
        $this->assertSame($database, DB::connection()->getDatabaseName());

        if ($connection === 'pgsql') {
            $this->assertSame('starter_api_test', $database, 'Seeder tests must target the dedicated test database.');
        }

        $this->assertNull(
            config("database.connections.{$connection}.url"),
            'Refusing to seed: the active connection resolves through a database URL.',
        );

        $this->assertSame(
            '',
            (string) getenv('TEST_TOKEN'),
            'Refusing to run migrate:fresh under a parallel runner (TEST_TOKEN present); seeder tests are exclusive.',
        );
    }

    private function invokeSeedRuntimeGuard(): void
    {
        $guard = new ReflectionMethod($this, 'assertIsolatedSeedRuntime');
        $guard->setAccessible(true);
        $guard->invoke($this);
    }

    /**
     * Runs `migrate:fresh --seed` as its own process so it never interacts
     * with the in-process connection, transaction state or pooled variants.
     * The child targets the same active test database, and any inherited DB_URL is stripped
     * (Symfony Process drops variables set to `false`). A URL smuggled in
     * through `.env` cannot reach the child either: the parent boots from
     * the same file and the setUp guard rejects any URL configuration
     * before the first fresh run.
     */
    private function runMigrateFreshSeed(): void
    {
        $connection = $this->databaseConnectionName();
        $connectionConfig = $this->databaseConnectionConfig($connection);
        $database = DB::connection()->getDatabaseName();

        $process = new Process(
            [PHP_BINARY, 'artisan', 'migrate:fresh', '--seed', '--force'],
            cwd: base_path(),
            env: [
                'DB_CONNECTION' => $connection,
                'DB_HOST' => $this->databaseConfigString($connectionConfig, 'host'),
                'DB_PORT' => $this->databaseConfigString($connectionConfig, 'port'),
                'DB_DATABASE' => $database,
                'DB_USERNAME' => $this->databaseConfigString($connectionConfig, 'username'),
                'DB_PASSWORD' => $this->databaseConfigString($connectionConfig, 'password'),
                'DB_URL' => false,
            ],
        );
        $process->setTimeout(120);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());
    }
}
