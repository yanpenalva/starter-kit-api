<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use App\Policies\UserPolicy;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

final class RbacAbilityMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.default');
        $this->assertContains($connection, ['pgsql', 'sqlite']);

        if ($connection === 'pgsql') {
            $database = config('database.connections.pgsql.database');
            $this->assertIsString($database);
            $this->assertSame($database, DB::connection()->getDatabaseName());
        }

        $this->seed(RbacCatalogueSeeder::class);
    }

    public function test_every_catalogue_permission_maps_to_a_gate_requiring_eligibility(): void
    {
        $permissions = Permission::values();

        $eligible = User::factory()->verified()->create();
        $eligible->givePermissionTo($permissions);

        $unprivileged = User::factory()->verified()->create();
        $unverified = User::factory()->unverified()->create();
        $unverified->givePermissionTo($permissions);
        $deactivated = User::factory()->deactivated()->create();
        $deactivated->givePermissionTo($permissions);

        foreach ($permissions as $permission) {
            $this->assertTrue($eligible->can($permission), "eligible / $permission");
            $this->assertFalse($unprivileged->can($permission), "unprivileged / $permission");
            $this->assertFalse($unverified->can($permission), "unverified / $permission");
            $this->assertFalse($deactivated->can($permission), "deactivated / $permission");
        }
    }

    public function test_roles_grant_the_gates_through_the_catalogue(): void
    {
        $admin = User::factory()->verified()->create();
        $admin->assignRole(Role::Admin->value);

        $plain = User::factory()->verified()->create();
        $plain->assignRole(Role::User->value);

        foreach (Permission::cases() as $permission) {
            $this->assertTrue($admin->can($permission->value), $permission->value);
            $this->assertFalse($plain->can($permission->value), $permission->value);
        }
    }

    public function test_set_status_denies_deactivating_the_last_active_admin(): void
    {
        $policy = new UserPolicy;

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersSetStatus->value);

        $soleAdmin = User::factory()->verified()->create();
        $soleAdmin->assignRole(Role::Admin->value);

        $this->assertFalse($policy->setStatus($actor, $soleAdmin, 'deactivated'));
    }

    public function test_set_status_never_allows_an_admin_to_deactivate_themselves(): void
    {
        $policy = new UserPolicy;

        $first = User::factory()->verified()->create();
        $first->assignRole(Role::Admin->value);
        $first->givePermissionTo(Permission::UsersSetStatus->value);

        $second = User::factory()->verified()->create();
        $second->assignRole(Role::Admin->value);

        $this->assertFalse($policy->setStatus($first, $first, 'deactivated'));
        $this->assertTrue($policy->setStatus($first, $second, 'deactivated'));
    }

    public function test_set_status_counts_only_active_admins_as_standing(): void
    {
        $policy = new UserPolicy;

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersSetStatus->value);

        $activeAdmin = User::factory()->verified()->create();
        $activeAdmin->assignRole(Role::Admin->value);

        $deactivatedAdmin = User::factory()->deactivated()->create();
        $deactivatedAdmin->assignRole(Role::Admin->value);

        $this->assertFalse($policy->setStatus($actor, $activeAdmin, 'deactivated'));

        $standby = User::factory()->verified()->create();
        $standby->assignRole(Role::Admin->value);

        $this->assertTrue($policy->setStatus($actor, $activeAdmin, 'deactivated'));
    }

    public function test_set_status_reactivation_only_requires_an_eligible_actor(): void
    {
        $policy = new UserPolicy;

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersSetStatus->value);

        $deactivated = User::factory()->deactivated()->create();
        $deactivated->assignRole(Role::Admin->value);

        $this->assertTrue($policy->setStatus($actor, $deactivated, 'active'));
    }

    public function test_set_status_deactivating_a_plain_account_is_allowed(): void
    {
        $policy = new UserPolicy;

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersSetStatus->value);

        $plain = User::factory()->verified()->create();
        $plain->assignRole(Role::User->value);

        $this->assertTrue($policy->setStatus($actor, $plain, 'deactivated'));
    }

    public function test_assign_roles_counts_only_active_admins_as_standing(): void
    {
        $policy = new UserPolicy;

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersAssignRoles->value);

        $activeAdmin = User::factory()->verified()->create();
        $activeAdmin->assignRole(Role::Admin->value);

        $deactivatedAdmin = User::factory()->deactivated()->create();
        $deactivatedAdmin->assignRole(Role::Admin->value);

        $this->assertFalse($policy->assignRoles($actor, $activeAdmin));

        $standby = User::factory()->verified()->create();
        $standby->assignRole(Role::Admin->value);

        $this->assertTrue($policy->assignRoles($actor, $activeAdmin));
    }

    public function test_seeder_rejects_a_permission_outside_the_approved_set(): void
    {
        PermissionModel::create(['name' => 'users.delete', 'guard_name' => 'web']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Permission catalogue diverged from the approved set.');

        $this->seed(RbacCatalogueSeeder::class);
    }

    public function test_seeder_rejects_a_rogue_entry_swapped_for_an_approved_one(): void
    {

        PermissionModel::where('name', Permission::UsersView->value)->delete();
        PermissionModel::create(['name' => 'users.delete', 'guard_name' => 'web']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Permission catalogue diverged from the approved set.');

        $this->seed(RbacCatalogueSeeder::class);
    }

    public function test_seeder_strips_permissions_granted_to_the_plain_role_outside_the_catalogue(): void
    {
        $plain = RoleModel::where('name', Role::User->value)->firstOrFail();
        $plain->givePermissionTo(Permission::UsersView->value);

        $this->seed(RbacCatalogueSeeder::class);

        $this->assertSame(0, (int) DB::table('role_has_permissions')->where('role_id', $plain->id)->count());
    }
}
