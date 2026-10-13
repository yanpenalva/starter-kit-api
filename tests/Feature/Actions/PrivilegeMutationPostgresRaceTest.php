<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Roles\StoreRole;
use App\Actions\Roles\UpdateRolePermissions;
use App\Actions\Users\SetUserStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role as PermissionRole;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Throwable;

final class PrivilegeMutationPostgresRaceTest extends TestCase
{
    private const TEST_DATABASE = 'starter_api_test';

    private const LOCK_WAIT_TIMEOUT_SECONDS = 10;

    private bool $databasePrepared = false;

    protected function setUp(): void
    {
        $configuredConnection = getenv('DB_CONNECTION');
        $configuredConnection = $configuredConnection === false
            ? ($_ENV['DB_CONNECTION'] ?? $_SERVER['DB_CONNECTION'] ?? null)
            : $configuredConnection;

        if ($configuredConnection !== 'pgsql' || ! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('Privilege action races require PostgreSQL.');
        }

        parent::setUp();

        if (DB::getDefaultConnection() !== 'pgsql') {
            $this->markTestSkipped('Privilege action races require PostgreSQL.');
        }

        if (config('database.connections.pgsql.database') !== self::TEST_DATABASE) {
            $this->markTestSkipped('Privilege action races only run on the isolated PostgreSQL test database.');
        }

        if (config('database.connections.pgsql.url') !== null
            || $this->environmentVariableIsSet('DB_URL')
            || $this->environmentVariableIsSet('DATABASE_URL')) {
            $this->markTestSkipped('Privilege action races do not run with a database URL override.');
        }

        if ($this->environmentVariableIsSet('TEST_TOKEN')) {
            $this->markTestSkipped('Privilege action races do not run in a parallel test worker.');
        }

        try {
            DB::connection('pgsql')->getPdo();
        } catch (Throwable) {
            $this->markTestSkipped('PostgreSQL is unavailable in this environment.');
        }

        $this->databasePrepared = true;
        Artisan::call('migrate:fresh', ['--database' => 'pgsql', '--force' => true]);
        $this->seed(RbacCatalogueSeeder::class);

        config(['database.connections.privilege_race' => config('database.connections.pgsql')]);
        DB::purge('privilege_race');
        DB::connection('privilege_race')->getPdo();
    }

    protected function tearDown(): void
    {
        if ($this->databasePrepared) {
            DB::setDefaultConnection('pgsql');
            DB::statement('DROP TRIGGER IF EXISTS privilege_race_user_status ON users');
            DB::statement('DROP TRIGGER IF EXISTS privilege_race_role_permission ON role_has_permissions');
            DB::statement('DROP FUNCTION IF EXISTS privilege_race_wait_for_competitor()');
            DB::statement('ALTER TABLE activity_log DROP CONSTRAINT IF EXISTS reject_privilege_race_audit');
            DB::purge('privilege_race');
            Artisan::call('migrate:fresh', ['--database' => 'pgsql', '--force' => true]);
        }

        parent::tearDown();
    }

    public function test_real_privilege_actions_serialize_concurrent_last_admin_and_permission_ceiling_changes(): void
    {
        $firstAdmin = User::factory()->verified()->create();
        $firstAdmin->assignRole(Role::Admin->value);
        $lastAdmin = User::factory()->verified()->create();
        $lastAdmin->assignRole(Role::Admin->value);
        $firstOperator = $this->operatorWithStatusPermission();
        $secondOperator = $this->operatorWithStatusPermission();

        $this->installCompetingMutationWaiter();
        DB::statement(<<<'SQL'
            CREATE TRIGGER privilege_race_user_status
            BEFORE UPDATE OF deactivated_at ON users
            FOR EACH ROW
            WHEN (OLD.deactivated_at IS NULL AND NEW.deactivated_at IS NOT NULL)
            EXECUTE FUNCTION privilege_race_wait_for_competitor()
            SQL);

        $auditCountBeforeLastAdminRace = $this->privilegeAuditCount();
        $lastAdminResults = $this->runConcurrentMutations(
            [
                'action' => 'set_user_status',
                'actor_id' => $this->modelId($firstOperator),
                'target_id' => $this->modelId($firstAdmin),
            ],
            fn () => app(SetUserStatus::class)->run($secondOperator, $lastAdmin, 'deactivated'),
        );

        $this->assertSame(['first' => 'success', 'second' => 'forbidden'], $lastAdminResults);
        $this->assertSame(1, User::query()->role(Role::Admin->value)->whereNull('deactivated_at')->count());
        $this->assertSame(1, User::query()
            ->whereKey([$firstAdmin->getKey(), $lastAdmin->getKey()])
            ->whereNotNull('deactivated_at')
            ->count());
        $this->assertSame($auditCountBeforeLastAdminRace + 1, $this->privilegeAuditCount());

        DB::statement('DROP TRIGGER privilege_race_user_status ON users');
        DB::statement('DROP FUNCTION privilege_race_wait_for_competitor()');

        $admin = User::factory()->verified()->create();
        $admin->assignRole(Role::Admin->value);
        $limitedRole = PermissionRole::query()->create([
            'name' => 'race-limited-manager',
            'guard_name' => 'web',
        ]);
        $limitedRole->syncPermissions([
            Permission::RolesUpdatePermissions->value,
            Permission::UsersManage->value,
        ]);
        $manager = User::factory()->verified()->create();
        $manager->assignRole($limitedRole);
        $grantTarget = PermissionRole::query()->create([
            'name' => 'race-grant-target',
            'guard_name' => 'web',
        ]);

        $this->installCompetingMutationWaiter();
        DB::statement(<<<'SQL'
            CREATE TRIGGER privilege_race_role_permission
            BEFORE DELETE ON role_has_permissions
            FOR EACH ROW
            EXECUTE FUNCTION privilege_race_wait_for_competitor()
            SQL);

        $auditCountBeforeCeilingRace = $this->privilegeAuditCount();
        $ceilingResults = $this->runConcurrentMutations(
            [
                'action' => 'update_role_permissions',
                'actor_id' => $this->modelId($admin),
                'target_id' => $this->modelId($limitedRole),
                'permission_names' => [Permission::RolesUpdatePermissions->value],
            ],
            fn () => app(UpdateRolePermissions::class)->run(
                $manager,
                $grantTarget,
                [Permission::UsersManage->value],
            ),
        );

        $permissionId = DB::table('permissions')
            ->where('name', Permission::UsersManage->value)
            ->where('guard_name', 'web')
            ->value('id');
        $this->assertDatabaseMissing('role_has_permissions', [
            'role_id' => $limitedRole->getKey(),
            'permission_id' => $permissionId,
        ]);
        $this->assertDatabaseMissing('role_has_permissions', [
            'role_id' => $grantTarget->getKey(),
            'permission_id' => $permissionId,
        ]);
        $freshManager = $manager->fresh();
        $this->assertInstanceOf(User::class, $freshManager);
        $this->assertFalse($freshManager->hasPermissionTo(Permission::UsersManage->value));
        $this->assertSame($auditCountBeforeCeilingRace + 1, $this->privilegeAuditCount());
        $this->assertSame(['first' => 'success', 'second' => 'forbidden'], $ceilingResults);

        DB::statement('DROP TRIGGER privilege_race_role_permission ON role_has_permissions');
        DB::statement('DROP FUNCTION privilege_race_wait_for_competitor()');

        $auditCountBeforeFailure = $this->privilegeAuditCount();
        DB::statement(
            "ALTER TABLE activity_log ADD CONSTRAINT reject_privilege_race_audit CHECK (event IS DISTINCT FROM 'privileged.change') NOT VALID",
        );

        try {
            try {
                $this->onConnection('privilege_race', fn () => app(StoreRole::class)->run($admin, 'race-audit-failure'));
                $this->fail('The StoreRole action must fail when its audit insert is rejected.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('reject_privilege_race_audit', $exception->getMessage());
            }

            $this->assertDatabaseMissing('roles', ['name' => 'race-audit-failure', 'guard_name' => 'web']);
            $this->assertSame($auditCountBeforeFailure, $this->privilegeAuditCount());
        } finally {
            DB::statement('ALTER TABLE activity_log DROP CONSTRAINT reject_privilege_race_audit');
        }
    }

    private function operatorWithStatusPermission(): User
    {
        $operator = User::factory()->verified()->create();
        $operator->givePermissionTo(Permission::UsersSetStatus->value);

        return $operator;
    }

    private function installCompetingMutationWaiter(): void
    {
        $timeoutSeconds = self::LOCK_WAIT_TIMEOUT_SECONDS;
        $processId = getmypid();
        $this->assertIsInt($processId);
        $applicationName = 'privilege-race-'.$processId;
        DB::connection('pgsql')->selectOne('SELECT set_config(?, ?, false)', [
            'application_name',
            $applicationName,
        ]);

        DB::statement(<<<SQL
            CREATE FUNCTION privilege_race_wait_for_competitor() RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                deadline timestamptz := clock_timestamp() + INTERVAL '{$timeoutSeconds} seconds';
            BEGIN
                LOOP
                    IF EXISTS (
                        SELECT 1
                        FROM pg_stat_activity
                        WHERE pid <> pg_backend_pid()
                            AND datname = current_database()
                            AND application_name = '{$applicationName}'
                            AND wait_event_type = 'Lock'
                            AND wait_event = 'advisory'
                    ) THEN
                        IF TG_OP = 'DELETE' THEN
                            RETURN OLD;
                        END IF;

                        RETURN NEW;
                    END IF;

                    IF clock_timestamp() >= deadline THEN
                        RAISE EXCEPTION 'No competing privilege action reached the advisory lock';
                    END IF;

                    PERFORM pg_sleep(0.01);
                END LOOP;
            END;
            $$
            SQL);
    }

    /**
     * @param  array{action: 'set_user_status'|'update_role_permissions', actor_id: int, target_id: int, permission_names?: list<string>}  $firstAction
     * @return array{first: string, second: string}
     */
    private function runConcurrentMutations(array $firstAction, \Closure $second): array
    {
        $process = new Process([PHP_BINARY, base_path('tests/Support/PrivilegeMutationWorker.php')]);
        $process->setInput(json_encode($firstAction, JSON_THROW_ON_ERROR)."\n");
        $process->setTimeout(self::LOCK_WAIT_TIMEOUT_SECONDS * 3);
        $process->setEnv([
            'APP_BASE_PATH' => base_path(),
            'APP_DEBUG' => 'false',
            'APP_ENV' => 'testing',
            'APP_KEY' => $this->configurationString('app.key'),
            'CACHE_STORE' => 'array',
            'DATABASE_URL' => false,
            'DB_CONNECTION' => 'pgsql',
            'DB_DATABASE' => $this->configurationString('database.connections.pgsql.database'),
            'DB_HOST' => $this->configurationString('database.connections.pgsql.host'),
            'DB_PASSWORD' => $this->configurationString('database.connections.pgsql.password'),
            'DB_PORT' => $this->configurationString('database.connections.pgsql.port'),
            'DB_URL' => false,
            'DB_USERNAME' => $this->configurationString('database.connections.pgsql.username'),
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
        ]);
        $process->start();

        $childBackendPid = $this->waitForWorker($process);
        $parentBackendPid = DB::connection('pgsql')->scalar('SELECT pg_backend_pid()');
        $this->assertIsInt($parentBackendPid);
        $this->assertNotSame($parentBackendPid, $childBackendPid);

        $parentException = null;

        try {
            $this->waitForChildPrivilegeLock($childBackendPid);
            $secondResult = $this->executeMutation($second);
        } catch (Throwable $exception) {
            $parentException = $exception;
            $process->stop(1);
        }

        if ($parentException instanceof Throwable) {
            throw $parentException;
        }

        $process->wait();
        $childResultMessage = $process->getOutput();
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput().$childResultMessage);
        $this->assertTrue(
            str_contains($childResultMessage, "result:success\n")
            || str_contains($childResultMessage, "result:forbidden\n"),
            $process->getErrorOutput().$childResultMessage,
        );

        return [
            'first' => str_contains($childResultMessage, "result:success\n") ? 'success' : 'forbidden',
            'second' => $secondResult,
        ];
    }

    private function waitForWorker(Process $process): int
    {
        $deadline = hrtime(true) + self::LOCK_WAIT_TIMEOUT_SECONDS * 1_000_000_000;
        $output = '';

        do {
            $output .= $process->getIncrementalOutput();
            $readyLine = strstr($output, "\n", true);

            if (is_string($readyLine) && str_starts_with($readyLine, 'ready:')) {
                $backendPid = filter_var(substr($readyLine, strlen('ready:')), FILTER_VALIDATE_INT);
                $this->assertNotFalse($backendPid);

                return $backendPid;
            }

            if ($process->isTerminated()) {
                $this->fail('The privilege action worker failed before connecting: '.$process->getErrorOutput().$process->getOutput());
            }

            usleep(10_000);
        } while (hrtime(true) < $deadline);

        $process->stop(1);
        $this->fail('The privilege action worker did not connect to PostgreSQL in time.');
    }

    private function waitForChildPrivilegeLock(int $childBackendPid): void
    {
        $deadline = hrtime(true) + self::LOCK_WAIT_TIMEOUT_SECONDS * 1_000_000_000;

        do {
            $lock = DB::connection('pgsql')->selectOne(
                "SELECT pid FROM pg_locks WHERE pid = ? AND locktype = 'advisory' AND granted",
                [$childBackendPid],
            );

            if ($lock !== null) {
                return;
            }

            usleep(10_000);
        } while (hrtime(true) < $deadline);

        $this->fail('The first real privilege action did not acquire its transaction advisory lock.');
    }

    private function executeMutation(\Closure $operation): string
    {
        try {
            $operation();

            return 'success';
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() === 403) {
                return 'forbidden';
            }

            throw $exception;
        }
    }

    private function modelId(User|PermissionRole $model): int
    {
        $id = $model->getKey();
        $this->assertIsInt($id);

        return $id;
    }

    private function configurationString(string $key): string
    {
        $value = config($key);

        if ($value === null) {
            return '';
        }

        if (! is_string($value) && ! is_int($value)) {
            $this->fail("Expected a string or integer configuration value for {$key}.");
        }

        return (string) $value;
    }

    private function privilegeAuditCount(): int
    {
        return Activity::query()->where('event', 'privileged.change')->count();
    }

    private function environmentVariableIsSet(string $name): bool
    {
        $value = getenv($name);

        return $value !== false && $value !== '';
    }

    /** @param \Closure(): mixed $operation */
    private function onConnection(string $connection, \Closure $operation): mixed
    {
        $previous = DB::getDefaultConnection();
        DB::setDefaultConnection($connection);

        try {
            return $operation();
        } finally {
            DB::setDefaultConnection($previous);
        }
    }
}
