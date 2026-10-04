<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
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
    protected function setUp(): void
    {
        parent::setUp();

        // Guard runs BEFORE any migrate/seed execution (none above this point):
        // refuse to touch anything outside the isolated testing environment.
        $this->assertTrue(app()->environment('testing'));
        $database = config('database.connections.pgsql.database');
        $this->assertIsString($database);
        $this->assertSame('starter_api_test', $database);
    }

    public function test_migrate_fresh_seed_command_succeeds_twice_and_keeps_the_canonical_catalogue(): void
    {
        $this->runMigrateFreshSeed();
        $this->runMigrateFreshSeed();

        $this->assertSame(8, DB::table('permissions')->where('guard_name', 'web')->count());
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

    /**
     * Runs `migrate:fresh --seed` as its own process so it never interacts
     * with the in-process connection, transaction state or pooled variants.
     * The child only ever reaches the dedicated test database: host/port and
     * password come from the isolated test stack, and the database name is
     * pinned to `starter_api_test`.
     */
    private function runMigrateFreshSeed(): void
    {
        $process = new Process(
            [PHP_BINARY, 'artisan', 'migrate:fresh', '--seed', '--force'],
            cwd: base_path(),
            env: [
                'DB_CONNECTION' => 'pgsql',
                'DB_HOST' => (string) (getenv('DB_HOST') ?: 'postgres-test'),
                'DB_PORT' => (string) (getenv('DB_PORT') ?: '5432'),
                'DB_DATABASE' => 'starter_api_test',
                'DB_USERNAME' => 'starter_api_test',
                'DB_PASSWORD' => (string) getenv('DB_PASSWORD'),
            ],
        );
        $process->setTimeout(120);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());
    }
}
