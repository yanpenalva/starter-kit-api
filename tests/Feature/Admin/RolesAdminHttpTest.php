<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use App\Support\Audit;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\Concern\ActsAsSanctumUser;
use Tests\TestCase;

/**
 * Wave B: HTTP slice of the roles admin surface. Index lists the catalogue,
 * update-permissions syncs a role's permission set under RolePolicy (admin
 * role immutable) inside the privilege transaction with a fail-closed audit
 * record.
 */
final class RolesAdminHttpTest extends TestCase
{
    use ActsAsSanctumUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.default');
        $this->assertContains($connection, ['pgsql', 'sqlite']);

        if ($connection === 'pgsql') {
            $database = config('database.connections.pgsql.database');
            $this->assertIsString($database);
            $this->assertSame('starter_api_test', $database);
        }

        $this->seed(RbacCatalogueSeeder::class);
    }

    public function test_anonymous_requests_return_401(): void
    {
        $this->getJson('/api/v1/roles')->assertStatus(401);

        $role = RoleModel::where('name', Role::User->value)->firstOrFail();
        $this->putJson("/api/v1/roles/{$role->id}/permissions", ['permissions' => []])->assertStatus(401);
    }

    public function test_index_returns_the_catalogue_with_permission_sets(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::RolesView->value);

        self::actingAsSanctum($actor);
        $response = $this->getJson('/api/v1/roles');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 2);

        /** @var array<int, array{name: string}> $dataPayload */
        $dataPayload = $response->json('data');
        $names = array_column($dataPayload, 'name');
        $this->assertSame([Role::Admin->value, Role::User->value], $names);

        $adminPermissions = $response->json('data.0.permissions');
        $this->assertIsArray($adminPermissions);

        $expected = Permission::values();
        sort($expected);
        $this->assertSame($expected, array_values($adminPermissions));

        $userPermissions = $response->json('data.1.permissions');
        $this->assertIsArray($userPermissions);
        $this->assertSame([], array_values($userPermissions));
    }

    public function test_index_applies_the_allowlisted_search_filter(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::RolesView->value);

        self::actingAsSanctum($actor);
        $response = $this->getJson('/api/v1/roles?search=adm');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', Role::Admin->value);
    }

    public function test_index_requires_the_roles_view_gate_with_eligibility(): void
    {
        $unprivileged = User::factory()->verified()->create();

        self::actingAsSanctum($unprivileged);
        $this->getJson('/api/v1/roles')
            ->assertStatus(403);

        $unverified = User::factory()->unverified()->create();
        $unverified->givePermissionTo(Permission::RolesView->value);

        self::actingAsSanctum($unverified);
        $this->getJson('/api/v1/roles')
            ->assertStatus(403);
    }

    public function test_update_permissions_syncs_the_role_set_with_audit(): void
    {
        $userRole = RoleModel::where('name', Role::User->value)->firstOrFail();

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::RolesUpdatePermissions->value);

        self::actingAsSanctum($actor);
        $response = $this->putJson("/api/v1/roles/{$userRole->id}/permissions", [
            'permissions' => [Permission::RolesView->value, Permission::UsersView->value],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', Role::User->value)
            ->assertJsonPath(
                'data.permissions',
                [Permission::RolesView->value, Permission::UsersView->value],
            );

        $this->assertSame(
            [Permission::RolesView->value, Permission::UsersView->value],
            $userRole->refresh()->permissions->pluck('name')->sort()->values()->all(),
        );

        $activity = Activity::query()
            ->where('event', Audit::EVENT_PRIVILEGED_CHANGE)
            ->where('subject_type', 'role')
            ->where('subject_id', $userRole->getKey())
            ->latest('id')
            ->firstOrFail();

        /** @var array<string, mixed> $properties */
        $properties = $activity->properties?->all() ?? [];
        $this->assertSame('user', $properties['actor_type']);
        $this->assertSame($actor->getKey(), $properties['actor_id']);
        $correlationId = $properties['correlation_id'];
        $this->assertIsString($correlationId);
        $this->assertTrue(Str::isUuid($correlationId));
    }

    public function test_update_permissions_keeps_the_admin_role_immutable(): void
    {
        $adminRole = RoleModel::where('name', Role::Admin->value)->firstOrFail();

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::RolesUpdatePermissions->value);

        self::actingAsSanctum($actor);
        $this->putJson("/api/v1/roles/{$adminRole->id}/permissions", [
            'permissions' => [Permission::UsersView->value],
        ])
            ->assertStatus(403);

        $expected = Permission::values();
        sort($expected);
        $this->assertSame($expected, $adminRole->refresh()->permissions->pluck('name')->sort()->values()->all());
    }

    public function test_update_permissions_rejects_unknown_permissions_with_422(): void
    {
        $userRole = RoleModel::where('name', Role::User->value)->firstOrFail();

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::RolesUpdatePermissions->value);

        self::actingAsSanctum($actor);
        $this->putJson("/api/v1/roles/{$userRole->id}/permissions", [
            'permissions' => ['users.delete'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('permissions.0');

        self::actingAsSanctum($actor);
        $this->putJson("/api/v1/roles/{$userRole->id}/permissions", [
            'permissions' => [],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('permissions');
    }

    public function test_update_permissions_returns_404_for_a_missing_role(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::RolesUpdatePermissions->value);

        self::actingAsSanctum($actor);
        $this->putJson('/api/v1/roles/999999/permissions', [
            'permissions' => [Permission::UsersView->value],
        ])
            ->assertStatus(404);
    }

    public function test_update_permissions_requires_the_gate_with_eligibility(): void
    {
        $userRole = RoleModel::where('name', Role::User->value)->firstOrFail();

        $unprivileged = User::factory()->verified()->create();

        self::actingAsSanctum($unprivileged);
        $this->putJson("/api/v1/roles/{$userRole->id}/permissions", [
            'permissions' => [Permission::UsersView->value],
        ])
            ->assertStatus(403);

        $unverified = User::factory()->unverified()->create();
        $unverified->givePermissionTo(Permission::RolesUpdatePermissions->value);

        self::actingAsSanctum($unverified);
        $this->putJson("/api/v1/roles/{$userRole->id}/permissions", [
            'permissions' => [Permission::UsersView->value],
        ])
            ->assertStatus(403);
    }
}
