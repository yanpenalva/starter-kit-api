<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Roles\DestroyRole;
use App\Actions\Roles\IndexRoles;
use App\Actions\Roles\RenameRole;
use App\Actions\Roles\StoreRole;
use App\Actions\Roles\UpdateRolePermissions;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Support\Audit;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role as RoleModel;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class RolesActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacCatalogueSeeder::class);
    }

    public function test_index_roles_returns_the_catalogue_with_permissions(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::RolesView->value);

        $page = app(IndexRoles::class)->run($actor, [], 15, 1);

        $this->assertSame([Role::Admin->value, Role::User->value], $page->getCollection()->pluck('name')->all());
    }

    public function test_index_roles_applies_the_search_filter(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::RolesView->value);

        $page = app(IndexRoles::class)->run($actor, ['search' => 'adm'], 15, 1);

        $this->assertSame([Role::Admin->value], $page->getCollection()->pluck('name')->all());
    }

    public function test_store_role_creates_a_catalogue_role_with_audit(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::RolesManage->value);

        $role = app(StoreRole::class)->run($actor, 'auditor');

        $this->assertSame('auditor', $role->name);
        $this->assertDatabaseHas('roles', ['name' => 'auditor', 'guard_name' => 'web']);
        $this->assertPrivilegedAudit($role, $actor, 'role.store');
    }

    public function test_destroy_role_deletes_unused_roles_with_audit(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::RolesManage->value);
        $role = RoleModel::query()->create(['name' => 'temporary', 'guard_name' => 'web']);

        app(DestroyRole::class)->run($actor, $role);

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertPrivilegedAudit($role, $actor, 'role.destroy');
    }

    public function test_destroy_role_rejects_roles_assigned_to_users(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::RolesManage->value);
        $role = RoleModel::where('name', Role::User->value)->firstOrFail();
        User::factory()->verified()->create()->assignRole($role);

        $this->assertActionStatus(422, static function () use ($actor, $role): void {
            app(DestroyRole::class)->run($actor, $role);
        });

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_update_role_permissions_rechecks_policy_inside_transaction_and_audits(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo([
            Permission::RolesUpdatePermissions->value,
            Permission::UsersView->value,
        ]);
        $role = RoleModel::where('name', Role::User->value)->firstOrFail();
        $transactionLevel = DB::transactionLevel();

        $policy = \Mockery::mock(new RolePolicy);
        $policy->shouldReceive('updatePermissions')
            ->once()
            ->andReturnUsing(static fn (): bool => DB::transactionLevel() > $transactionLevel);
        $this->app->instance(RolePolicy::class, $policy);

        $updated = app(UpdateRolePermissions::class)->run($actor, $role, [Permission::UsersView->value]);

        $this->assertSame([Permission::UsersView->value], $updated->permissions->pluck('name')->sort()->values()->all());
        $this->assertPrivilegedAudit($role, $actor, 'role.update_permissions');
    }

    public function test_update_role_permissions_rejects_admin_when_called_directly(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::RolesUpdatePermissions->value);
        $role = RoleModel::query()->where('name', Role::Admin->value)->firstOrFail();
        $permissionsBefore = $role->permissions->pluck('name')->sort()->values()->all();

        $this->assertNotSame([Permission::UsersView->value], $permissionsBefore);
        $this->assertActionStatus(403, static function () use ($actor, $role): void {
            app(UpdateRolePermissions::class)->run($actor, $role, [Permission::UsersView->value]);
        });

        $this->assertSame(
            $permissionsBefore,
            $role->refresh()->permissions->pluck('name')->sort()->values()->all(),
        );
    }

    public function test_rename_role_rejects_a_duplicate_name_when_called_directly(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::RolesManage->value);
        $role = RoleModel::query()->create(['name' => 'auditor', 'guard_name' => 'web']);
        RoleModel::query()->create(['name' => 'records-auditor', 'guard_name' => 'web']);

        try {
            app(RenameRole::class)->run($actor, $role, 'records-auditor');
            $this->fail('Expected a duplicate role name to return a validation error.');
        } catch (ValidationException $exception) {
            $this->assertSame(422, $exception->status);
            $this->assertArrayHasKey('name', $exception->errors());
        }

        $this->assertSame('auditor', $role->refresh()->name);
    }

    public function test_update_role_permissions_throws_not_found_for_missing_roles(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::RolesUpdatePermissions->value);
        $missing = new RoleModel(['id' => 999999]);

        $this->expectException(ModelNotFoundException::class);

        app(UpdateRolePermissions::class)->run($actor, $missing, [Permission::UsersView->value]);
    }

    /**
     * @param  callable(): mixed  $call
     */
    private function assertActionStatus(int $status, callable $call): void
    {
        try {
            $call();
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode());

            return;
        }

        $this->fail('Expected an HTTP error response.');
    }

    private function assertPrivilegedAudit(RoleModel $role, User $actor, string $action): void
    {
        $activity = Activity::query()
            ->where('event', Audit::EVENT_PRIVILEGED_CHANGE)
            ->where('subject_type', 'role')
            ->where('subject_id', $role->getKey())
            ->latest('id')
            ->firstOrFail();

        /** @var array<string, mixed> $properties */
        $properties = $activity->properties?->all() ?? [];

        $this->assertSame('user', $properties['actor_type']);
        $this->assertSame($actor->getKey(), $properties['actor_id']);
        $this->assertSame($action, $properties['action']);
    }
}
