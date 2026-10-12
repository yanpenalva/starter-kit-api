<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\Role as RoleEnum;
use App\Models\User;
use App\Support\Audit;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\Concern\ActsAsSanctumUser;
use Tests\TestCase;

final class CustomRoleManagementTest extends TestCase
{
    use ActsAsSanctumUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacCatalogueSeeder::class);
    }

    public function test_role_detail_returns_permissions_and_requires_roles_view(): void
    {
        $role = Role::query()->create(['name' => 'auditor', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::UsersView->value);

        $viewer = User::factory()->verified()->create();
        $viewer->givePermissionTo(Permission::RolesView->value);
        self::actingAsSanctum($viewer);

        $this->getJson("/api/v1/roles/{$role->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'auditor')
            ->assertJsonPath('data.guard_name', 'web')
            ->assertJsonPath('data.permissions', [Permission::UsersView->value]);

        $unprivileged = User::factory()->verified()->create();
        self::actingAsSanctum($unprivileged);
        $this->getJson("/api/v1/roles/{$role->id}")->assertForbidden();
    }

    public function test_custom_role_can_be_renamed_and_change_is_audited(): void
    {
        $role = Role::query()->create(['name' => 'auditor', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::UsersView->value);
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::RolesManage->value);
        self::actingAsSanctum($actor);

        $this->patchJson("/api/v1/roles/{$role->id}", ['name' => 'records-auditor'])
            ->assertOk()
            ->assertJsonPath('data.name', 'records-auditor')
            ->assertJsonPath('data.permissions', [Permission::UsersView->value]);

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'records-auditor']);
        $this->assertSame(
            [Permission::UsersView->value],
            $role->refresh()->permissions->pluck('name')->sort()->values()->all(),
        );

        $activity = Activity::query()
            ->where('event', Audit::EVENT_PRIVILEGED_CHANGE)
            ->where('subject_type', 'role')
            ->where('subject_id', $role->id)
            ->firstOrFail();

        /** @var array<string, mixed> $properties */
        $properties = $activity->properties?->all() ?? [];
        $this->assertSame('user', $properties['actor_type'] ?? null);
        $this->assertSame($actor->id, $properties['actor_id'] ?? null);
    }

    public function test_rename_rejects_a_duplicate_name(): void
    {
        $role = Role::query()->create(['name' => 'auditor', 'guard_name' => 'web']);
        Role::query()->create(['name' => 'records-auditor', 'guard_name' => 'web']);
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::RolesManage->value);
        self::actingAsSanctum($actor);

        $this->patchJson("/api/v1/roles/{$role->id}", ['name' => 'records-auditor'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'auditor']);
    }

    public function test_rename_requires_roles_manage_and_an_eligible_actor(): void
    {
        $role = Role::query()->create(['name' => 'auditor', 'guard_name' => 'web']);

        $this->patchJson("/api/v1/roles/{$role->id}", ['name' => 'records-auditor'])
            ->assertUnauthorized();

        $unprivileged = User::factory()->verified()->create();
        self::actingAsSanctum($unprivileged);
        $this->patchJson("/api/v1/roles/{$role->id}", ['name' => 'records-auditor'])
            ->assertForbidden();

        $unverified = User::factory()->unverified()->create();
        $unverified->givePermissionTo(Permission::RolesManage->value);
        self::actingAsSanctum($unverified);
        $this->patchJson("/api/v1/roles/{$role->id}", ['name' => 'records-auditor'])
            ->assertForbidden();
    }

    public function test_admin_role_cannot_be_renamed_or_deleted(): void
    {
        $adminRole = Role::query()->where('name', RoleEnum::Admin->value)->firstOrFail();
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::RolesManage->value);
        self::actingAsSanctum($actor);

        $this->patchJson("/api/v1/roles/{$adminRole->id}", ['name' => 'root'])
            ->assertForbidden();
        $this->deleteJson("/api/v1/roles/{$adminRole->id}")->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $adminRole->id, 'name' => RoleEnum::Admin->value]);
    }

    public function test_system_user_role_cannot_be_renamed(): void
    {
        $userRole = Role::query()->where('name', RoleEnum::User->value)->firstOrFail();
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::RolesManage->value);
        self::actingAsSanctum($actor);

        $this->patchJson("/api/v1/roles/{$userRole->id}", ['name' => 'member'])
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $userRole->id, 'name' => RoleEnum::User->value]);
    }
}
