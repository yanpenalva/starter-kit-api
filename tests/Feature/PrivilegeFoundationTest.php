<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\BootstrapAdmin;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class PrivilegeFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'pgsql') {
            $database = config('database.connections.pgsql.database');
            $this->assertIsString($database);
            $this->assertSame('starter_api_test', $database);
        }

        $this->seed(RbacCatalogueSeeder::class);
    }

    public function test_policies_deny_self_elevation_and_last_admin_removal(): void
    {
        $policy = new UserPolicy;

        $soleAdmin = User::factory()->verified()->create();
        $soleAdmin->assignRole('admin');

        $this->assertFalse($policy->assignRoles($soleAdmin, $soleAdmin));

        $secondAdmin = User::factory()->verified()->create();
        $secondAdmin->assignRole('admin');

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo('users.assign-roles');

        $this->assertTrue($policy->assignRoles($actor, $soleAdmin));
    }

    public function test_every_user_ability_requires_a_verified_active_actor(): void
    {
        $policy = new UserPolicy;
        $target = User::factory()->verified()->create();

        $abilities = [
            'view' => 'users.view',
            'update' => 'users.update',
            'readCpf' => 'users.read-cpf',
            'setStatus' => 'users.set-status',
            'assignRoles' => 'users.assign-roles',
        ];

        foreach (['unverified', 'deactivated'] as $state) {
            $actor = User::factory()->$state()->create();
            $actor->givePermissionTo(array_values($abilities));

            $this->assertFalse($policy->view($actor, $target), $state);
            $this->assertFalse($policy->update($actor, $target), $state);
            $this->assertFalse($policy->readCpf($actor, $target), $state);
            $this->assertFalse($policy->setStatus($actor, $target, 'deactivated'), $state);
            $this->assertFalse($policy->assignRoles($actor, $target), $state);
        }
    }

    public function test_role_policy_abilities_require_a_verified_active_actor(): void
    {
        $policy = new RolePolicy;
        $role = Role::where('name', 'user')->firstOrFail();

        foreach (['unverified', 'deactivated'] as $state) {
            $actor = User::factory()->$state()->create();
            $actor->givePermissionTo(['roles.view', 'roles.update-permissions']);

            $this->assertFalse($policy->view($actor), "roles.view / $state");
            $this->assertFalse($policy->updatePermissions($actor, $role, []), "roles.update-permissions / $state");
        }
    }

    public function test_direct_permission_cannot_self_assign_roles(): void
    {
        $policy = new UserPolicy;

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo('users.assign-roles');

        $this->assertFalse($actor->hasRole('admin'));
        $this->assertFalse($policy->assignRoles($actor, $actor));
    }

    public function test_role_policy_keeps_the_admin_role_immutable(): void
    {
        $policy = new RolePolicy;

        $actor = User::factory()->create();
        $actor->givePermissionTo('roles.update-permissions');

        $adminRole = Role::where('name', 'admin')->firstOrFail();

        $this->assertFalse($policy->updatePermissions($actor, $adminRole, []));

        $actor->givePermissionTo('roles.view');
        $this->assertTrue($policy->view($actor));
    }

    public function test_role_policy_limits_permissions_to_the_actors_own_permissions(): void
    {
        $policy = new RolePolicy;
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(['roles.update-permissions', 'roles.view']);
        $userRole = Role::where('name', 'user')->firstOrFail();

        $this->assertTrue($policy->updatePermissions($actor, $userRole, ['roles.view']));
        $this->assertFalse($policy->updatePermissions($actor, $userRole, ['users.view']));
        $this->assertFalse($policy->updatePermissions($actor, $userRole, ['roles.update-permissions', 'users.view']));

        $admin = User::factory()->verified()->create();
        $admin->assignRole('admin');
        $this->assertTrue($policy->updatePermissions($admin, $userRole, ['users.manage']));
    }

    public function test_bootstrap_grants_admin_and_writes_the_audited_envelope(): void
    {
        $target = User::factory()->create();
        $targetKey = $target->getKey();
        $this->assertIsInt($targetKey);

        $result = app(BootstrapAdmin::class)->run($targetKey, false);

        $this->assertFalse($result['already_admin']);
        $this->assertTrue($target->refresh()->hasRole('admin'));

        $activity = Activity::query()
            ->where('event', 'cli.bootstrap')
            ->where('subject_type', 'user')
            ->where('subject_id', $target->getKey())
            ->firstOrFail();

        $propertiesCollection = $activity->properties;
        $this->assertNotNull($propertiesCollection);

        $properties = $propertiesCollection->all();
        $this->assertArrayHasKey('correlation_id', $properties);
        $this->assertSame('cli', $properties['actor_type']);

        foreach (['cpf', 'email', 'password', 'token'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $properties);
        }
    }

    public function test_bootstrap_rejects_missing_unverified_and_deactivated_accounts(): void
    {
        $bootstrap = app(BootstrapAdmin::class);

        $this->expectException(\RuntimeException::class);
        $bootstrap->run(999999, false);
    }

    public function test_bootstrap_rejects_unverified_accounts(): void
    {
        $unverified = User::factory()->unverified()->create();
        $accountKey = $unverified->getKey();
        $this->assertIsInt($accountKey);

        $this->expectException(\RuntimeException::class);
        app(BootstrapAdmin::class)->run($accountKey, false);
    }

    public function test_bootstrap_rejects_deactivated_accounts(): void
    {
        $deactivated = User::factory()->deactivated()->create();
        $accountKey = $deactivated->getKey();
        $this->assertIsInt($accountKey);

        $this->expectException(\RuntimeException::class);
        app(BootstrapAdmin::class)->run($accountKey, false);
    }

    public function test_bootstrap_is_idempotent_without_duplicate_audits(): void
    {
        $target = User::factory()->create();
        $bootstrap = app(BootstrapAdmin::class);
        $targetKey = $target->getKey();
        $this->assertIsInt($targetKey);

        $bootstrap->run($targetKey, false);
        $second = $bootstrap->run($targetKey, false);

        $this->assertTrue($second['already_admin']);

        $audits = Activity::query()
            ->where('event', 'cli.bootstrap')
            ->where('subject_id', $target->getKey())
            ->count();

        $this->assertSame(1, $audits);
    }

    public function test_audit_failure_rolls_the_mutation_back(): void
    {
        $target = User::factory()->create();
        $targetKey = $target->getKey();
        $this->assertIsInt($targetKey);

        Schema::drop('activity_log');

        try {
            app(BootstrapAdmin::class)->run($targetKey, false);
            $this->fail('The mutation must fail when the audit write fails.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('activity_log', $exception->getMessage());
        }

        $target->refresh();

        $this->assertFalse($target->hasRole('admin'));
        $this->assertSame(0, DB::table('model_has_roles')->where('model_id', $target->getKey())->count());
    }

    public function test_production_default_denies_without_the_confirmation_flag(): void
    {
        $target = User::factory()->create();

        $this->app->instance('env', 'production');

        try {
            $targetKey = $target->getKey();
            $this->assertIsInt($targetKey);

            app(BootstrapAdmin::class)->run($targetKey, false);
            $this->fail('Production bootstrap must require the confirmation flag.');
        } catch (\DomainException) {
            $target->refresh();
            $this->assertFalse($target->hasRole('admin'));
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_production_accepts_the_confirmation_flag_for_a_synthetic_account(): void
    {
        $target = User::factory()->create();

        $this->app->instance('env', 'production');

        $targetKey = $target->getKey();
        $this->assertIsInt($targetKey);

        try {
            $result = app(BootstrapAdmin::class)->run($targetKey, true);

            $this->assertFalse($result['already_admin']);
            $this->assertTrue($target->refresh()->hasRole('admin'));
        } finally {
            $this->app->instance('env', 'testing');
        }
    }
}
