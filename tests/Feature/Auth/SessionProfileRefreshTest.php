<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as PermissionRole;
use Tests\Concern\ActsAsSanctumUser;
use Tests\TestCase;

final class SessionProfileRefreshTest extends TestCase
{
    use ActsAsSanctumUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertContains(config('database.default'), ['pgsql', 'sqlite']);
        $this->seed(RbacCatalogueSeeder::class);
    }

    public function test_profile_get_uses_current_database_identity_roles_and_account_state(): void
    {
        $user = User::factory()->create([
            'email' => 'session-refresh@example.test',
            'name' => 'Before refresh',
            'email_verified_at' => now(),
            'deactivated_at' => null,
        ]);
        $user = User::query()->whereKey($user->getKey())->firstOrFail();
        $user->assignRole(Role::User->value);
        $user->load('roles');

        self::actingAsSanctum($user);
        $this->getJson('/api/v1/auth/my-profile')
            ->assertOk()
            ->assertJsonPath('data.name', 'Before refresh')
            ->assertJsonPath('data.roles', [Role::User->value]);

        $adminRole = PermissionRole::query()
            ->where('name', Role::Admin->value)
            ->where('guard_name', 'web')
            ->firstOrFail();

        DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $user->getKey())
            ->delete();
        DB::table('model_has_roles')->insert([
            'role_id' => $adminRole->getKey(),
            'model_type' => User::class,
            'model_id' => $user->getKey(),
        ]);
        DB::table('users')->where('id', $user->getKey())->update([
            'name' => 'After refresh',
        ]);

        self::actingAsSanctum($user);
        $expectedPermissions = Permission::values();
        sort($expectedPermissions);

        $this->getJson('/api/v1/auth/my-profile')
            ->assertOk()
            ->assertJsonPath('data.name', 'After refresh')
            ->assertJsonPath('data.roles', [Role::Admin->value])
            ->assertJsonPath('data.permissions', $expectedPermissions);

        DB::table('users')->where('id', $user->getKey())->update([
            'deactivated_at' => now(),
        ]);

        self::actingAsSanctum($user);
        $this->getJson('/api/v1/auth/my-profile')->assertForbidden();
    }

    public function test_profile_returns_permission_union_without_duplicates_for_multiple_roles(): void
    {
        $viewer = PermissionRole::query()->create(['name' => 'viewer', 'guard_name' => 'web']);
        $viewer->givePermissionTo([Permission::UsersView->value, Permission::LogsView->value]);

        $auditor = PermissionRole::query()->create(['name' => 'auditor', 'guard_name' => 'web']);
        $auditor->givePermissionTo([Permission::LogsView->value, Permission::RolesView->value]);

        $user = User::factory()->verified()->create();
        $user->assignRole([$viewer->name, $auditor->name]);
        $user = User::query()->whereKey($user->getKey())->firstOrFail();

        $expectedPermissions = [
            Permission::UsersView->value,
            Permission::LogsView->value,
            Permission::RolesView->value,
        ];
        sort($expectedPermissions);

        self::actingAsSanctum($user);
        $this->getJson('/api/v1/auth/my-profile')
            ->assertOk()
            ->assertJsonPath('data.roles', ['auditor', 'viewer'])
            ->assertJsonPath('data.permissions', $expectedPermissions);
    }
}
