<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Actions\BootstrapAdmin;
use App\Actions\Roles\DestroyRole;
use App\Actions\Roles\StoreRole;
use App\Actions\Roles\UpdateRolePermissions;
use App\Actions\Users\AssignUserRoles;
use App\Actions\Users\DestroyUser;
use App\Actions\Users\SetUserStatus;
use App\Actions\Users\StoreUser;
use App\Actions\Users\UpdateUser;
use App\Enums\Permission;
use App\Enums\Role as RoleEnum;
use App\Models\User;
use App\Support\Audit;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\Concern\ActsAsSanctumUser;
use Tests\TestCase;

final class AuditDistinctActionsTest extends TestCase
{
    use ActsAsSanctumUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacCatalogueSeeder::class);
    }

    public function test_audit_defines_distinct_action_codes_allowlist(): void
    {
        $this->assertSame([
            Audit::ACTION_USER_STORE,
            Audit::ACTION_USER_UPDATE,
            Audit::ACTION_USER_DESTROY,
            Audit::ACTION_USER_STATUS,
            Audit::ACTION_USER_ASSIGN_ROLES,
            Audit::ACTION_ROLE_STORE,
            Audit::ACTION_ROLE_UPDATE_PERMISSIONS,
            Audit::ACTION_ROLE_DESTROY,
            Audit::ACTION_ROLE_RENAME,
            Audit::ACTION_BOOTSTRAP,
        ], Audit::allowedActions());
    }

    public function test_minimal_properties_preserves_allowed_action_and_drops_unapproved_ones(): void
    {
        $allowed = Audit::minimalProperties([
            'correlation_id' => '00000000-0000-0000-0000-000000000000',
            'actor_type' => 'user',
            'actor_id' => 1,
            'action' => Audit::ACTION_USER_STORE,
            'unapproved_action' => 'malicious.hack',
        ]);

        $this->assertSame(Audit::ACTION_USER_STORE, $allowed['action'] ?? null);
        $this->assertArrayNotHasKey('unapproved_action', $allowed);

        $dropped = Audit::minimalProperties([
            'action' => 'malicious.hack',
        ]);

        $this->assertArrayNotHasKey('action', $dropped);
    }

    public function test_audit_rejects_an_unapproved_action_code(): void
    {
        $this->expectException(RuntimeException::class);

        Audit::record(
            Audit::EVENT_PRIVILEGED_CHANGE,
            'user',
            1,
            '00000000-0000-0000-0000-000000000000',
            ['action' => 'malicious.hack'],
        );
    }

    public function test_actions_record_distinct_action_codes_in_audit_properties(): void
    {
        $admin = User::factory()->verified()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $user = app(StoreUser::class)->run($admin, [
            'name' => 'Audited User',
            'email' => 'audited@example.test',
            'password' => 'secret1234',
            'cpf' => null,
            'active' => true,
        ]);

        app(UpdateUser::class)->run($admin, $user, ['name' => 'Updated Audited User']);
        app(SetUserStatus::class)->run($admin, $user, 'deactivated');
        app(AssignUserRoles::class)->run($admin, $user, [RoleEnum::User->value]);
        app(DestroyUser::class)->run($admin, $user);

        $userActions = Activity::query()
            ->where('subject_type', 'user')
            ->where('subject_id', $user->id)
            ->orderBy('id')
            ->get()
            ->map(static fn (Activity $activity): mixed => $activity->properties['action'] ?? null)
            ->all();

        $this->assertSame([
            Audit::ACTION_USER_STORE,
            Audit::ACTION_USER_UPDATE,
            Audit::ACTION_USER_STATUS,
            Audit::ACTION_USER_ASSIGN_ROLES,
            Audit::ACTION_USER_DESTROY,
        ], $userActions);

        $role = app(StoreRole::class)->run($admin, 'audited_custom_role');
        app(UpdateRolePermissions::class)->run($admin, $role, [Permission::UsersView->value]);
        app(DestroyRole::class)->run($admin, $role);

        $roleActions = Activity::query()
            ->where('subject_type', 'role')
            ->where('subject_id', $role->id)
            ->orderBy('id')
            ->get()
            ->map(static fn (Activity $activity): mixed => $activity->properties['action'] ?? null)
            ->all();

        $this->assertSame([
            Audit::ACTION_ROLE_STORE,
            Audit::ACTION_ROLE_UPDATE_PERMISSIONS,
            Audit::ACTION_ROLE_DESTROY,
        ], $roleActions);

        $bootstrapped = User::factory()->verified()->create();
        app(BootstrapAdmin::class)->run($bootstrapped->id, false);

        $bootstrap = Activity::query()
            ->where('event', Audit::EVENT_CLI_BOOTSTRAP)
            ->where('subject_id', $bootstrapped->id)
            ->firstOrFail();

        $this->assertSame(Audit::ACTION_BOOTSTRAP, $bootstrap->properties['action'] ?? null);
    }

    public function test_audit_feed_exposes_action_in_properties_and_allows_filtering(): void
    {
        $admin = User::factory()->verified()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        self::actingAsSanctum($admin);

        app(StoreUser::class)->run($admin, [
            'name' => 'Audited Feed User',
            'email' => 'feed@example.test',
            'password' => 'secret1234',
            'cpf' => null,
            'active' => true,
        ]);

        $response = $this->getJson('/api/v1/activity-log?action='.Audit::ACTION_USER_STORE);
        $response->assertOk()->assertJsonPath('data.0.properties.action', Audit::ACTION_USER_STORE);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        $this->getJson('/api/v1/activity-log?action=unknown.action')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('action');
    }

    public function test_retention_policy_is_preserved_at_90_days(): void
    {
        $this->assertSame(90, config('activitylog.clean_after_days'));
    }
}
