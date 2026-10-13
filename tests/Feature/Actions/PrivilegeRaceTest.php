<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Roles\DestroyRole;
use App\Actions\Roles\RenameRole;
use App\Actions\Roles\StoreRole;
use App\Actions\Roles\UpdateRolePermissions;
use App\Actions\Users\AssignUserRoles;
use App\Actions\Users\DestroyUser;
use App\Actions\Users\SetUserStatus;
use App\Actions\Users\StoreUser;
use App\Actions\Users\UpdateUser;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as PermissionRole;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class PrivilegeRaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacCatalogueSeeder::class);
    }

    public function test_store_user_rechecks_manager_permission_inside_the_transaction(): void
    {
        $actor = $this->actorWith(Permission::UsersManage);
        $this->revokePermission($actor, Permission::UsersManage);

        $this->assertForbidden(fn () => app(StoreUser::class)->run($actor, [
            'name' => 'Race target',
            'email' => 'race-store-user@example.test',
            'cpf' => null,
            'password' => 'secret123',
            'active' => true,
        ]));

        $this->assertDatabaseMissing('users', ['email' => 'race-store-user@example.test']);
    }

    public function test_update_user_rechecks_update_permission_inside_the_transaction(): void
    {
        $actor = $this->actorWith(Permission::UsersUpdate);
        $target = User::factory()->create(['name' => 'Before']);
        $this->revokePermission($actor, Permission::UsersUpdate);

        $this->assertForbidden(fn () => app(UpdateUser::class)->run($actor, $target, ['name' => 'After']));

        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Before']);
    }

    public function test_set_user_status_rechecks_status_permission_inside_the_transaction(): void
    {
        $actor = $this->actorWith(Permission::UsersSetStatus);
        $target = User::factory()->create(['deactivated_at' => null]);
        $this->revokePermission($actor, Permission::UsersSetStatus);

        $this->assertForbidden(fn () => app(SetUserStatus::class)->run($actor, $target, 'deactivated'));

        $this->assertDatabaseHas('users', ['id' => $target->id, 'deactivated_at' => null]);
    }

    public function test_assign_user_roles_rechecks_role_permission_inside_the_transaction(): void
    {
        $actor = $this->actorWith(Permission::UsersAssignRoles);
        $target = User::factory()->create();
        $this->revokePermission($actor, Permission::UsersAssignRoles);

        $this->assertForbidden(fn () => app(AssignUserRoles::class)->run($actor, $target, [Role::User->value]));

        $this->assertDatabaseMissing('model_has_roles', [
            'model_type' => User::class,
            'model_id' => $target->getKey(),
        ]);
    }

    public function test_destroy_user_rechecks_manager_permission_inside_the_transaction(): void
    {
        $actor = $this->actorWith(Permission::UsersManage);
        $target = User::factory()->create();
        $this->revokePermission($actor, Permission::UsersManage);

        $this->assertForbidden(fn () => app(DestroyUser::class)->run($actor, $target));

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_store_role_rechecks_manager_permission_inside_the_transaction(): void
    {
        $actor = $this->actorWith(Permission::RolesManage);
        $this->revokePermission($actor, Permission::RolesManage);

        $this->assertForbidden(fn () => app(StoreRole::class)->run($actor, 'race-role'));

        $this->assertDatabaseMissing('roles', ['name' => 'race-role']);
    }

    public function test_rename_role_rechecks_manager_permission_inside_the_transaction(): void
    {
        $actor = $this->actorWith(Permission::RolesManage);
        $role = PermissionRole::query()->create(['name' => 'race-role', 'guard_name' => 'web']);
        $this->revokePermission($actor, Permission::RolesManage);

        $this->assertForbidden(fn () => app(RenameRole::class)->run($actor, $role, 'renamed-role'));

        $this->assertDatabaseHas('roles', ['id' => $role->getKey(), 'name' => 'race-role']);
    }

    public function test_update_role_permissions_rechecks_authority_inside_the_transaction(): void
    {
        $actor = $this->actorWith(Permission::RolesUpdatePermissions);
        $role = PermissionRole::query()->where('name', Role::User->value)->firstOrFail();
        $this->revokePermission($actor, Permission::RolesUpdatePermissions);

        $this->assertForbidden(fn () => app(UpdateRolePermissions::class)->run($actor, $role, [Permission::UsersView->value]));

        $this->assertDatabaseMissing('role_has_permissions', [
            'role_id' => $role->getKey(),
            'permission_id' => DB::table('permissions')->where('name', Permission::UsersView->value)->value('id'),
        ]);
    }

    public function test_destroy_role_rechecks_manager_permission_inside_the_transaction(): void
    {
        $actor = $this->actorWith(Permission::RolesManage);
        $role = PermissionRole::query()->create(['name' => 'race-role', 'guard_name' => 'web']);
        $this->revokePermission($actor, Permission::RolesManage);

        $this->assertForbidden(fn () => app(DestroyRole::class)->run($actor, $role));

        $this->assertDatabaseHas('roles', ['id' => $role->getKey()]);
    }

    private function actorWith(Permission $permission): User
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo($permission->value);
        $actor->load(['permissions', 'roles']);

        return $actor;
    }

    private function revokePermission(User $actor, Permission $permission): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', $permission->value)
            ->where('guard_name', 'web')
            ->value('id');
        $this->assertNotNull($permissionId);

        DB::table('model_has_permissions')
            ->where('model_type', User::class)
            ->where('model_id', $actor->getKey())
            ->where('permission_id', $permissionId)
            ->delete();

        $this->assertTrue($actor->hasPermissionTo($permission->value));
    }

    /** @param callable(): mixed $operation */
    private function assertForbidden(callable $operation): void
    {
        try {
            $operation();
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());

            return;
        }

        $this->fail('Expected the mutation to reject the revoked authority.');
    }
}
