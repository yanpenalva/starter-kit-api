<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use App\Support\Audit;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\Concern\ActsAsSanctumUser;
use Tests\TestCase;

/**
 * Wave B: HTTP slice of the users admin CRUD. Authorization is proven end
 * to end — catalogue gates for the ability, UserPolicy object protections
 * (self, last active admin) inside the privilege transaction, and the
 * fail-closed audit trail per mutation.
 */
final class UsersAdminHttpTest extends TestCase
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
        $this->getJson('/api/v1/users')->assertStatus(401);
        $this->getJson('/api/v1/users/1')->assertStatus(401);
        $this->patchJson('/api/v1/users/1', ['name' => 'X'])->assertStatus(401);
        $this->postJson('/api/v1/users/1/status', ['status' => 'deactivated'])->assertStatus(401);
        $this->putJson('/api/v1/users/1/roles', ['roles' => ['user']])->assertStatus(401);
        $this->postJson('/api/v1/users', [])->assertStatus(401);
        $this->deleteJson('/api/v1/users/1')->assertStatus(401);
    }

    public function test_create_user_validates_and_persists_without_authenticating(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo('users.manage');
        self::actingAsSanctum($actor);

        $this->postJson('/api/v1/users', [])->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'cpf', 'password', 'active']);

        User::factory()->create(['email' => 'duplicate@example.test']);
        $this->postJson('/api/v1/users', [
            'name' => 'Duplicado',
            'email' => 'DUPLICATE@example.test',
            'cpf' => '12345678902',
            'password' => 'test-password-01',
            'active' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Novo Usuário',
            'email' => 'novo@example.test',
            'cpf' => '12345678901',
            'password' => 'test-password-01',
            'active' => true,
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'Novo Usuário');
        $this->assertDatabaseHas('users', ['email' => 'novo@example.test', 'cpf' => '12345678901']);
        $createdId = $response->json('data.id');
        $this->assertIsInt($createdId);
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $createdId]);
        $this->assertAuthenticatedAs($actor);
    }

    public function test_create_user_requires_manage_permission_and_eligible_actor(): void
    {
        $payload = [
            'name' => 'New User',
            'email' => 'new@example.test',
            'cpf' => '98765432100',
            'password' => 'test-password-01',
            'active' => true,
        ];
        $unprivileged = User::factory()->verified()->create();
        self::actingAsSanctum($unprivileged);
        $this->postJson('/api/v1/users', $payload)->assertStatus(403);

        $unverified = User::factory()->unverified()->create();
        $unverified->givePermissionTo('users.manage');
        self::actingAsSanctum($unverified);
        $this->postJson('/api/v1/users', $payload)->assertStatus(403);

        $deactivated = User::factory()->deactivated()->create();
        $deactivated->givePermissionTo('users.manage');
        self::actingAsSanctum($deactivated);
        $this->postJson('/api/v1/users', $payload)->assertStatus(403);
    }

    public function test_delete_user_denies_self_and_last_admin(): void
    {
        $admin = User::factory()->verified()->create();
        $admin->assignRole(Role::Admin->value);
        $admin->givePermissionTo('users.manage');
        self::actingAsSanctum($admin);

        $this->deleteJson("/api/v1/users/{$admin->id}")->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $admin->removeRole(Role::Admin->value);

        $lastAdmin = User::factory()->verified()->create();
        $lastAdmin->assignRole(Role::Admin->value);
        $manager = User::factory()->verified()->create();
        $manager->givePermissionTo('users.manage');
        self::actingAsSanctum($manager);
        $this->deleteJson("/api/v1/users/{$lastAdmin->id}")->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $lastAdmin->id]);
    }

    public function test_delete_user_removes_non_admin_target_and_requires_manage_permission(): void
    {
        $target = User::factory()->verified()->create();
        $actor = User::factory()->verified()->create();

        self::actingAsSanctum($actor);
        $this->deleteJson("/api/v1/users/{$target->id}")->assertStatus(403);

        $actor->givePermissionTo('users.manage');
        self::actingAsSanctum($actor);
        $this->deleteJson("/api/v1/users/{$target->id}")->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_index_paginates_and_applies_allowlisted_filters(): void
    {
        $alice = User::factory()->verified()->create(['name' => 'Alice', 'email' => 'alice@example.test']);
        $alice->assignRole(Role::User->value);

        $bob = User::factory()->deactivated()->create(['name' => 'Bob', 'email' => 'bob@example.test']);

        User::factory()->verified()->create(['name' => 'Carol', 'email' => 'carol@other.test']);

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersView->value);

        self::actingAsSanctum($actor);
        $response = $this->getJson('/api/v1/users?search=example.test&per_page=2');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.per_page', 2);

        $this->assertTrue(Str::isUuid((string) $response->headers->get('X-Correlation-Id')));

        /** @var array<int, array{id: int}> $idsPayload */
        $idsPayload = $response->json('data');
        $ids = array_column($idsPayload, 'id');
        $this->assertContains($alice->id, $ids);
        $this->assertContains($bob->id, $ids);

        self::actingAsSanctum($actor);
        $filtered = $this->getJson('/api/v1/users?status=deactivated');

        $filtered->assertOk();
        /** @var array<int, array{id: int}> $filteredPayload */
        $filteredPayload = $filtered->json('data');
        $this->assertSame([$bob->id], array_column($filteredPayload, 'id'));

        self::actingAsSanctum($actor);
        $byRole = $this->getJson('/api/v1/users?role='.Role::User->value);

        $byRole->assertOk();
        /** @var array<int, array{id: int}> $byRolePayload */
        $byRolePayload = $byRole->json('data');
        $this->assertSame([$alice->id], array_column($byRolePayload, 'id'));
    }

    public function test_index_loads_user_roles_without_n_plus_one_queries(): void
    {
        $alice = User::factory()->verified()->create();
        $alice->assignRole(Role::User->value);

        $bob = User::factory()->verified()->create();
        $bob->assignRole(Role::Admin->value);

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersView->value);

        self::actingAsSanctum($actor);

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $response = $this->getJson('/api/v1/users');
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        $response->assertOk()
            ->assertJsonPath('data.0.roles', [Role::User->value])
            ->assertJsonPath('data.1.roles', [Role::Admin->value]);

        $roleQueries = array_filter(
            $queries,
            static fn (array $query): bool => str_contains(strtolower($query['query']), 'model_has_roles'),
        );

        $this->assertCount(2, $roleQueries);
    }

    public function test_index_ignores_unknown_filters_and_bounds_per_page(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersView->value);

        self::actingAsSanctum($actor);
        $this->getJson('/api/v1/users?unlisted=bogus')
            ->assertOk();

        self::actingAsSanctum($actor);
        $this->getJson('/api/v1/users?per_page=101')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    public function test_index_requires_the_users_view_gate_with_eligibility(): void
    {
        $unprivileged = User::factory()->verified()->create();

        self::actingAsSanctum($unprivileged);
        $this->getJson('/api/v1/users')
            ->assertStatus(403)
            ->assertExactJson(['message' => 'Acesso não autorizado.']);

        $unverified = User::factory()->unverified()->create();
        $unverified->givePermissionTo(Permission::UsersView->value);

        self::actingAsSanctum($unverified);
        $this->getJson('/api/v1/users')
            ->assertStatus(403);

        $deactivated = User::factory()->deactivated()->create();
        $deactivated->givePermissionTo(Permission::UsersView->value);

        self::actingAsSanctum($deactivated);
        $this->getJson('/api/v1/users')
            ->assertStatus(403);
    }

    public function test_show_exposes_cpf_only_to_read_cpf_eligible_actors(): void
    {
        $target = User::factory()->verified()->withCpf()->create();

        $viewer = User::factory()->verified()->create();
        $viewer->givePermissionTo(Permission::UsersView->value);

        self::actingAsSanctum($viewer);
        $payload = $this->getJson("/api/v1/users/{$target->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $target->id)
            ->assertJsonPath('data.email', $target->email)
            ->json('data');

        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('cpf', $payload);

        $cpfReader = User::factory()->verified()->create();
        $cpfReader->givePermissionTo([Permission::UsersView->value, Permission::UsersReadCpf->value]);

        self::actingAsSanctum($cpfReader);

        $this->getJson("/api/v1/users/{$target->id}")
            ->assertOk()
            ->assertJsonPath('data.cpf', $target->cpf);
    }

    public function test_show_returns_404_for_a_missing_user(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersView->value);

        self::actingAsSanctum($actor);
        $this->getJson('/api/v1/users/999999')
            ->assertStatus(404);
    }

    public function test_update_changes_basic_attributes_and_writes_audit(): void
    {
        $target = User::factory()->verified()->create();

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersUpdate->value);

        self::actingAsSanctum($actor);
        $response = $this->patchJson("/api/v1/users/{$target->id}", [
            'name' => 'Nome Atualizado',
            'email' => 'atualizado@example.test',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Nome Atualizado')
            ->assertJsonPath('data.email', 'atualizado@example.test');

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'name' => 'Nome Atualizado',
            'email' => 'atualizado@example.test',
        ]);

        $this->assertPrivilegedAudit($target, $actor);
    }

    public function test_update_rejects_duplicate_email_and_cpf(): void
    {
        $existing = User::factory()->verified()->create([
            'email' => 'taken@example.test',
            'cpf' => '12345678901',
        ]);
        $target = User::factory()->verified()->create(['cpf' => '10987654321']);

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersUpdate->value);

        self::actingAsSanctum($actor);
        $this->patchJson("/api/v1/users/{$target->id}", ['email' => 'TAKEN@example.test'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->patchJson("/api/v1/users/{$target->id}", ['cpf' => '12345678901'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cpf']);

        $this->patchJson("/api/v1/users/{$target->id}", [
            'email' => $target->email,
            'cpf' => $target->cpf,
        ])->assertOk();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'email' => $target->email]);
    }

    public function test_update_requires_the_users_update_gate(): void
    {
        $target = User::factory()->verified()->create();

        $unprivileged = User::factory()->verified()->create();

        self::actingAsSanctum($unprivileged);
        $this->patchJson("/api/v1/users/{$target->id}", ['name' => 'Invasão'])
            ->assertStatus(403);

        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => $target->name]);
    }

    public function test_show_requires_the_users_view_gate(): void
    {
        $target = User::factory()->verified()->create();

        $unprivileged = User::factory()->verified()->create();

        self::actingAsSanctum($unprivileged);
        $this->getJson("/api/v1/users/{$target->id}")->assertStatus(403);
    }

    public function test_set_status_deactivates_and_reactivates_with_audit(): void
    {
        $target = User::factory()->verified()->create();

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersSetStatus->value);

        self::actingAsSanctum($actor);
        $this->postJson("/api/v1/users/{$target->id}/status", ['status' => 'deactivated'])
            ->assertOk()
            ->assertJsonPath('data.deactivated_at', function (mixed $value): bool {
                return is_string($value);
            });

        $this->assertNotNull($target->refresh()->deactivated_at);

        self::actingAsSanctum($actor);
        $this->postJson("/api/v1/users/{$target->id}/status", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.deactivated_at', null);

        $this->assertNull($target->refresh()->deactivated_at);

        $this->assertSame(2, $this->auditCount($target, $actor));
    }

    public function test_set_status_rejects_unknown_transitions_with_422(): void
    {
        $target = User::factory()->verified()->create();

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersSetStatus->value);

        self::actingAsSanctum($actor);
        $this->postJson("/api/v1/users/{$target->id}/status", ['status' => 'bogus'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_set_status_denies_self_deactivation(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->assignRole(Role::Admin->value);

        self::actingAsSanctum($actor);
        $this->postJson("/api/v1/users/{$actor->id}/status", ['status' => 'deactivated'])
            ->assertStatus(403);

        $this->assertNull($actor->refresh()->deactivated_at);
    }

    public function test_set_status_preserves_the_last_active_admin(): void
    {
        $soleAdmin = User::factory()->verified()->create();
        $soleAdmin->assignRole(Role::Admin->value);

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersSetStatus->value);

        self::actingAsSanctum($actor);
        $this->postJson("/api/v1/users/{$soleAdmin->id}/status", ['status' => 'deactivated'])
            ->assertStatus(403);

        $this->assertNull($soleAdmin->refresh()->deactivated_at);

        $backup = User::factory()->verified()->create();
        $backup->assignRole(Role::Admin->value);

        self::actingAsSanctum($actor);
        $this->postJson("/api/v1/users/{$soleAdmin->id}/status", ['status' => 'deactivated'])
            ->assertOk();

        $this->assertNotNull($soleAdmin->refresh()->deactivated_at);
    }

    public function test_assign_roles_replaces_the_target_role_set_with_audit(): void
    {
        $target = User::factory()->verified()->create();
        $target->assignRole(Role::User->value);

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersAssignRoles->value);

        self::actingAsSanctum($actor);
        $response = $this->putJson("/api/v1/users/{$target->id}/roles", ['roles' => [Role::Admin->value]]);

        $response->assertOk()
            ->assertJsonPath('data.roles', [Role::Admin->value]);

        $this->assertTrue($target->refresh()->hasRole(Role::Admin->value));
        $this->assertFalse($target->hasRole(Role::User->value));

        $this->assertPrivilegedAudit($target, $actor);
    }

    public function test_assign_roles_rejects_non_catalogue_roles_with_422(): void
    {
        $target = User::factory()->verified()->create();

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersAssignRoles->value);

        self::actingAsSanctum($actor);
        $this->putJson("/api/v1/users/{$target->id}/roles", ['roles' => ['ghost']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('roles.0');

        self::actingAsSanctum($actor);
        $this->putJson("/api/v1/users/{$target->id}/roles", ['roles' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('roles');
    }

    public function test_assign_roles_denies_self_assignment(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersAssignRoles->value);

        self::actingAsSanctum($actor);
        $this->putJson("/api/v1/users/{$actor->id}/roles", ['roles' => [Role::Admin->value]])
            ->assertStatus(403);

        $this->assertFalse($actor->refresh()->hasRole(Role::Admin->value));
    }

    public function test_assign_roles_preserves_the_last_active_admin(): void
    {
        $soleAdmin = User::factory()->verified()->create();
        $soleAdmin->assignRole(Role::Admin->value);

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersAssignRoles->value);

        self::actingAsSanctum($actor);
        $this->putJson("/api/v1/users/{$soleAdmin->id}/roles", ['roles' => [Role::User->value]])
            ->assertStatus(403);

        $this->assertTrue($soleAdmin->refresh()->hasRole(Role::Admin->value));

        $backup = User::factory()->verified()->create();
        $backup->assignRole(Role::Admin->value);

        self::actingAsSanctum($actor);
        $this->putJson("/api/v1/users/{$soleAdmin->id}/roles", ['roles' => [Role::User->value]])
            ->assertOk();

        $this->assertFalse($soleAdmin->refresh()->hasRole(Role::Admin->value));
    }

    public function test_audit_failure_rolls_the_mutation_back(): void
    {
        $target = User::factory()->verified()->create();

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersSetStatus->value);

        Schema::drop('activity_log');

        self::actingAsSanctum($actor);
        $this->postJson("/api/v1/users/{$target->id}/status", ['status' => 'deactivated'])
            ->assertStatus(500);

        $this->assertNull($target->refresh()->deactivated_at);
    }

    private function auditCount(User $target, User $actor): int
    {
        return Activity::query()
            ->where('event', Audit::EVENT_PRIVILEGED_CHANGE)
            ->where('subject_type', 'user')
            ->where('subject_id', $target->getKey())
            ->whereJsonContains('properties', ['actor_id' => $actor->getKey()])
            ->count();
    }

    private function assertPrivilegedAudit(User $target, User $actor): void
    {
        $activity = Activity::query()
            ->where('event', Audit::EVENT_PRIVILEGED_CHANGE)
            ->where('subject_type', 'user')
            ->where('subject_id', $target->getKey())
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('privilege', $activity->log_name);
        $this->assertSame(Audit::EVENT_PRIVILEGED_CHANGE, $activity->description);

        /** @var array<string, mixed> $properties */
        $properties = $activity->properties?->all() ?? [];

        $this->assertSame('user', $properties['actor_type']);
        $this->assertSame($actor->getKey(), $properties['actor_id']);
        $this->assertArrayHasKey('correlation_id', $properties);
        $correlationId = $properties['correlation_id'];
        $this->assertIsString($correlationId);
        $this->assertTrue(Str::isUuid($correlationId));

        foreach (['cpf', 'email', 'password', 'token'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $properties);
        }
    }
}
