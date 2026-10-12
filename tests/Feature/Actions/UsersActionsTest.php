<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Users\AssignUserRoles;
use App\Actions\Users\DestroyUser;
use App\Actions\Users\IndexUsers;
use App\Actions\Users\SetUserStatus;
use App\Actions\Users\ShowUser;
use App\Actions\Users\StoreUser;
use App\Actions\Users\UpdateUser;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use App\Support\Audit;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role as SpatieRole;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class UsersActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacCatalogueSeeder::class);
    }

    public function test_index_users_returns_paginated_users_with_roles(): void
    {
        $alice = User::factory()->verified()->create(['name' => 'Alice', 'email' => 'alice@example.test']);
        $alice->assignRole(Role::User->value);

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersView->value);

        $page = app(IndexUsers::class)->run($actor, ['search' => 'example.test'], 15, 1);

        $this->assertContains($alice->id, $page->getCollection()->pluck('id')->all());

        $found = $page->getCollection()->firstWhere('id', $alice->id);
        $this->assertInstanceOf(User::class, $found);
        $this->assertTrue($found->relationLoaded('roles'));
    }

    public function test_store_user_creates_active_and_deactivated_accounts_with_audit(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersManage->value);

        $active = app(StoreUser::class)->run($actor, [
            'name' => 'Active User',
            'email' => 'active@example.test',
            'cpf' => '12345678901',
            'password' => 'test-password-01',
            'active' => true,
        ]);

        $this->assertNull($active->deactivated_at);
        $this->assertDatabaseHas('users', ['email' => 'active@example.test']);
        $this->assertPrivilegedAudit($active, $actor);

        $deactivated = app(StoreUser::class)->run($actor, [
            'name' => 'Inactive User',
            'email' => 'inactive@example.test',
            'cpf' => '12345678902',
            'password' => 'test-password-01',
            'active' => false,
        ]);

        $this->assertNotNull($deactivated->deactivated_at);
    }

    public function test_show_user_returns_the_target_for_eligible_actors(): void
    {
        $target = User::factory()->verified()->create();
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersView->value);

        $this->assertTrue(app(ShowUser::class)->run($actor, $target)->is($target));
    }

    public function test_update_user_changes_attributes_with_audit(): void
    {
        $target = User::factory()->verified()->create();
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersUpdate->value);

        $updated = app(UpdateUser::class)->run($actor, $target, ['name' => 'Renamed']);

        $this->assertSame('Renamed', $updated->name);
        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Renamed']);
        $this->assertPrivilegedAudit($target, $actor);
    }

    public function test_changing_email_resets_verification_but_unchanged_email_does_not(): void
    {
        $target = User::factory()->verified()->create(['email' => 'verified@example.test']);
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersManage->value);
        $verifiedAt = $target->email_verified_at;

        app(UpdateUser::class)->run($actor, $target, ['email' => 'new-address@example.test']);
        $this->assertNull($target->fresh()?->email_verified_at);

        $target->forceFill(['email_verified_at' => $verifiedAt])->save();
        app(UpdateUser::class)->run($actor, $target, ['email' => 'new-address@example.test']);
        $this->assertNotNull($target->fresh()?->email_verified_at);
    }

    public function test_update_action_protects_privileged_email_even_when_called_directly(): void
    {
        $target = User::factory()->verified()->create(['email' => 'action-admin@example.test']);
        $target->assignRole(Role::Admin->value);
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersUpdate->value);

        $this->assertActionStatus(403, static fn (): mixed => app(UpdateUser::class)->run(
            $actor,
            $target,
            ['email' => 'action-hijack@example.test'],
        ));
        $this->assertSame('action-admin@example.test', $target->fresh()?->email);

        $operator = User::factory()->verified()->create();
        $operator->givePermissionTo(Permission::UsersManage->value);
        $updated = app(UpdateUser::class)->run($operator, $target, ['email' => 'changed-admin@example.test']);

        $this->assertSame('changed-admin@example.test', $updated->email);
    }

    public function test_update_action_protects_privileged_credentials_even_when_called_directly(): void
    {
        $target = User::factory()->verified()->create();
        $target->givePermissionTo(Permission::RolesManage->value);
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersUpdate->value);
        $originalPassword = $target->password;

        $this->assertActionStatus(403, static fn (): mixed => app(UpdateUser::class)->run(
            $actor,
            $target,
            ['password' => 'hijacked-password-12'],
        ));

        $this->assertSame($originalPassword, $target->fresh()?->password);

        $operator = User::factory()->verified()->create();
        $operator->assignRole(Role::Admin->value);
        $updated = app(UpdateUser::class)->run($operator, $target, ['password' => 'legitimate-password-12']);

        $this->assertNotSame($originalPassword, $updated->password);
    }

    public function test_destroy_user_deletes_the_target_with_audit(): void
    {
        $target = User::factory()->verified()->create();
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersManage->value);

        app(DestroyUser::class)->run($actor, $target);

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertPrivilegedAudit($target, $actor);
    }

    public function test_destroy_user_denies_self_deletion_and_the_last_admin(): void
    {
        $admin = User::factory()->verified()->create();
        $admin->assignRole(Role::Admin->value);
        $admin->givePermissionTo(Permission::UsersManage->value);

        $this->assertActionStatus(403, static function () use ($admin): void {
            app(DestroyUser::class)->run($admin, $admin);
        });
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_set_user_status_deactivates_and_reactivates_with_audit(): void
    {
        $target = User::factory()->verified()->create();
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersSetStatus->value);

        $this->assertNotNull(app(SetUserStatus::class)->run($actor, $target, 'deactivated')->deactivated_at);
        $this->assertNull(app(SetUserStatus::class)->run($actor, $target, 'active')->deactivated_at);
        $this->assertPrivilegedAudit($target, $actor);
    }

    public function test_set_user_status_denies_self_deactivation(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersSetStatus->value);

        $this->assertActionStatus(403, static fn (): mixed => app(SetUserStatus::class)->run($actor, $actor, 'deactivated'));

        $this->assertNull($actor->refresh()->deactivated_at);
    }

    public function test_assign_user_roles_replaces_the_role_set_with_audit(): void
    {
        $target = User::factory()->verified()->create();
        $target->assignRole(Role::User->value);

        $actor = User::factory()->verified()->create();
        $actor->assignRole(Role::Admin->value);
        $actor->givePermissionTo(Permission::UsersAssignRoles->value);

        $updated = app(AssignUserRoles::class)->run($actor, $target, [Role::Admin->value]);

        $this->assertTrue($updated->hasRole(Role::Admin->value));
        $this->assertFalse($updated->hasRole(Role::User->value));
        $this->assertPrivilegedAudit($target, $actor);
    }

    public function test_assign_user_roles_denies_delegated_operator_escalating_to_admin(): void
    {
        $target = User::factory()->verified()->create();
        $target->assignRole(Role::User->value);

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersAssignRoles->value);

        $this->assertActionStatus(403, static fn (): mixed => app(AssignUserRoles::class)->run($actor, $target, [Role::Admin->value]));

        $this->assertFalse($target->refresh()->hasRole(Role::Admin->value));
    }

    public function test_assign_user_roles_denies_delegated_operator_assigning_role_with_unheld_permissions(): void
    {
        $customRole = SpatieRole::create(['name' => 'manager', 'guard_name' => 'web']);
        $customRole->givePermissionTo(Permission::UsersManage->value);

        $target = User::factory()->verified()->create();
        $target->assignRole(Role::User->value);

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersAssignRoles->value);
        $actor->givePermissionTo(Permission::UsersView->value);

        $this->assertActionStatus(403, static fn (): mixed => app(AssignUserRoles::class)->run($actor, $target, ['manager']));

        $this->assertFalse($target->refresh()->hasRole('manager'));
    }

    public function test_assign_user_roles_allows_delegated_operator_within_grant_ceiling(): void
    {
        $customRole = SpatieRole::create(['name' => 'viewer', 'guard_name' => 'web']);
        $customRole->givePermissionTo(Permission::UsersView->value);

        $target = User::factory()->verified()->create();
        $target->assignRole(Role::User->value);

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersAssignRoles->value);
        $actor->givePermissionTo(Permission::UsersView->value);

        $updated = app(AssignUserRoles::class)->run($actor, $target, ['viewer']);

        $this->assertTrue($updated->hasRole('viewer'));
    }

    public function test_assign_user_roles_denies_whole_list_when_one_role_exceeds_ceiling(): void
    {
        $manager = SpatieRole::create(['name' => 'manager', 'guard_name' => 'web']);
        $manager->givePermissionTo(Permission::UsersManage->value);
        $viewer = SpatieRole::create(['name' => 'viewer', 'guard_name' => 'web']);
        $viewer->givePermissionTo(Permission::UsersView->value);

        $target = User::factory()->verified()->create();
        $target->assignRole(Role::User->value);

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersAssignRoles->value);
        $actor->givePermissionTo(Permission::UsersView->value);

        $this->assertActionStatus(403, static fn (): mixed => app(AssignUserRoles::class)->run($actor, $target, ['viewer', 'manager']));

        $fresh = $target->refresh();
        $this->assertFalse($fresh->hasRole('viewer'));
        $this->assertFalse($fresh->hasRole('manager'));
        $this->assertTrue($fresh->hasRole(Role::User->value));
    }

    public function test_assign_user_roles_applies_ceiling_to_permissions_inherited_via_role(): void
    {
        $manager = SpatieRole::create(['name' => 'manager', 'guard_name' => 'web']);
        $manager->givePermissionTo(Permission::UsersManage->value);
        $viewer = SpatieRole::create(['name' => 'viewer', 'guard_name' => 'web']);
        $viewer->givePermissionTo(Permission::UsersView->value);

        $operator = SpatieRole::create(['name' => 'operator', 'guard_name' => 'web']);
        $operator->givePermissionTo([Permission::UsersAssignRoles->value, Permission::UsersView->value]);

        $target = User::factory()->verified()->create();
        $target->assignRole(Role::User->value);

        $actor = User::factory()->verified()->create();
        $actor->assignRole('operator');

        $this->assertFalse($actor->getDirectPermissions()->contains('name', Permission::UsersView->value));

        $updated = app(AssignUserRoles::class)->run($actor, $target, ['viewer']);
        $this->assertTrue($updated->hasRole('viewer'));

        $this->assertActionStatus(403, static fn (): mixed => app(AssignUserRoles::class)->run($actor, $target, ['manager']));
        $this->assertFalse($target->refresh()->hasRole('manager'));
    }

    public function test_assign_user_roles_denies_nonexistent_role_reaching_policy_through_action(): void
    {
        $target = User::factory()->verified()->create();
        $target->assignRole(Role::User->value);

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersAssignRoles->value);

        $this->assertActionStatus(403, static fn (): mixed => app(AssignUserRoles::class)->run($actor, $target, ['ghost']));

        $this->assertTrue($target->refresh()->hasRole(Role::User->value));
    }

    public function test_assign_user_roles_allows_admin_to_grant_custom_role(): void
    {
        $manager = SpatieRole::create(['name' => 'manager', 'guard_name' => 'web']);
        $manager->givePermissionTo(Permission::UsersManage->value);
        $viewer = SpatieRole::create(['name' => 'viewer', 'guard_name' => 'web']);
        $viewer->givePermissionTo(Permission::UsersView->value);

        $target = User::factory()->verified()->create();
        $target->assignRole(Role::User->value);

        $actor = User::factory()->verified()->create();
        $actor->assignRole(Role::Admin->value);

        $updated = app(AssignUserRoles::class)->run($actor, $target, ['manager']);

        $this->assertTrue($updated->hasRole('manager'));
    }

    public function test_assign_user_roles_denies_self_assignment(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersAssignRoles->value);

        $this->assertActionStatus(403, static fn (): mixed => app(AssignUserRoles::class)->run($actor, $actor, [Role::Admin->value]));

        $this->assertFalse($actor->refresh()->hasRole(Role::Admin->value));
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

    private function assertPrivilegedAudit(User $target, User $actor): void
    {
        $activity = Activity::query()
            ->where('event', Audit::EVENT_PRIVILEGED_CHANGE)
            ->where('subject_type', 'user')
            ->where('subject_id', $target->getKey())
            ->latest('id')
            ->firstOrFail();

        /** @var array<string, mixed> $properties */
        $properties = $activity->properties?->all() ?? [];

        $this->assertSame('user', $properties['actor_type']);
        $this->assertSame($actor->getKey(), $properties['actor_id']);
    }
}
