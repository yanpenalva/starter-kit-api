<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\VerifyEmail;
use App\Support\Audit;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\Concern\ActsAsSanctumUser;
use Tests\TestCase;

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
        $actor->givePermissionTo([Permission::UsersManage->value, Permission::UsersAssignRoles->value]);
        self::actingAsSanctum($actor);

        $this->postJson('/api/v1/users', [])->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'cpf', 'password', 'active', 'roles']);

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
            'roles' => [Role::User->value],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Novo Usuário')
            ->assertJsonPath('data.roles', [Role::User->value]);
        $this->assertDatabaseHas('users', ['email' => 'novo@example.test', 'cpf' => '12345678901']);
        $createdId = $response->json('data.id');
        $this->assertIsInt($createdId);
        $this->assertDatabaseHas('model_has_roles', ['model_id' => $createdId]);
        $this->assertAuthenticatedAs($actor);
    }

    public function test_create_user_requires_non_empty_roles_and_does_not_create_an_account(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->assignRole(Role::Admin->value);
        self::actingAsSanctum($actor);

        $payload = [
            'name' => 'Sem Perfil',
            'cpf' => '12345678904',
            'password' => 'test-password-01',
            'active' => true,
        ];

        $this->postJson('/api/v1/users', array_replace($payload, ['email' => 'sem-perfil@example.test']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('roles');
        $this->assertDatabaseMissing('users', ['email' => 'sem-perfil@example.test']);

        $this->postJson('/api/v1/users', array_replace($payload, [
            'email' => 'lista-vazia@example.test',
            'cpf' => '12345678914',
            'roles' => [],
        ]))->assertUnprocessable()->assertJsonValidationErrors('roles');
        $this->assertDatabaseMissing('users', ['email' => 'lista-vazia@example.test']);
    }

    public function test_create_user_assigns_multiple_roles(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->assignRole(Role::Admin->value);
        self::actingAsSanctum($actor);

        $created = $this->postJson('/api/v1/users', [
            'name' => 'Admin Comum',
            'email' => 'admin-comum@example.test',
            'cpf' => '12345678903',
            'password' => 'test-password-01',
            'active' => true,
            'roles' => [Role::User->value, Role::Admin->value],
        ])->assertCreated()->assertJsonPath('data.roles', [Role::Admin->value, Role::User->value]);

        $createdId = $created->json('data.id');
        $this->assertIsInt($createdId);
        $createdUser = User::findOrFail($createdId);
        $this->assertTrue($createdUser->hasRole(Role::Admin->value));
        $this->assertTrue($createdUser->hasRole(Role::User->value));
        $actions = Activity::query()
            ->where('subject_type', 'user')
            ->where('subject_id', $createdUser->id)
            ->orderBy('id')
            ->get()
            ->map(static fn (Activity $activity): mixed => $activity->properties?->get('action'))
            ->all();
        $this->assertSame([Audit::ACTION_USER_ASSIGN_ROLES, Audit::ACTION_USER_STORE], $actions);

    }

    public function test_create_user_with_roles_requires_assign_roles_permission(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersManage->value);
        self::actingAsSanctum($actor);

        $this->postJson('/api/v1/users', [
            'name' => 'Sem Atribuição',
            'email' => 'sem-atribuicao@example.test',
            'cpf' => '12345678905',
            'password' => 'test-password-01',
            'active' => true,
            'roles' => [Role::User->value],
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'sem-atribuicao@example.test']);
    }

    public function test_create_user_rejects_unknown_and_duplicate_roles_with_422(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->assignRole(Role::Admin->value);
        self::actingAsSanctum($actor);

        $payload = [
            'name' => 'Papéis inválidos',
            'cpf' => '12345678906',
            'password' => 'test-password-01',
            'active' => true,
        ];

        $this->postJson('/api/v1/users', $payload + [
            'email' => 'papel-inexistente@example.test',
            'roles' => ['ghost'],
        ])->assertUnprocessable()->assertJsonValidationErrors('roles.0');

        $this->postJson('/api/v1/users', $payload + [
            'email' => 'papel-duplicado@example.test',
            'roles' => [Role::User->value, Role::User->value],
        ])->assertUnprocessable()->assertJsonValidationErrors('roles.1');

        $this->assertDatabaseMissing('users', ['email' => 'papel-inexistente@example.test']);
        $this->assertDatabaseMissing('users', ['email' => 'papel-duplicado@example.test']);
    }

    public function test_create_user_denies_roles_above_the_actor_grant_ceiling(): void
    {
        $role = SpatieRole::create(['name' => 'view-users', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::UsersView->value);

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo([Permission::UsersManage->value, Permission::UsersAssignRoles->value]);
        self::actingAsSanctum($actor);

        $this->postJson('/api/v1/users', [
            'name' => 'Escalação negada',
            'email' => 'escalacao-negada@example.test',
            'cpf' => '12345678907',
            'password' => 'test-password-01',
            'active' => true,
            'roles' => [$role->name],
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'escalacao-negada@example.test']);
    }

    public function test_create_user_allows_delegated_roles_within_the_actor_grant_ceiling(): void
    {
        $role = SpatieRole::create(['name' => 'reports-viewer', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::UsersView->value);

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo([
            Permission::UsersManage->value,
            Permission::UsersAssignRoles->value,
            Permission::UsersView->value,
        ]);
        self::actingAsSanctum($actor);

        $this->postJson('/api/v1/users', [
            'name' => 'Operador delegado',
            'email' => 'operador-delegado@example.test',
            'cpf' => '12345678909',
            'password' => 'test-password-01',
            'active' => true,
            'roles' => [$role->name],
        ])->assertCreated()->assertJsonPath('data.roles', [$role->name]);
    }

    public function test_create_user_rolls_back_account_and_roles_when_audit_recording_fails(): void
    {
        Notification::fake();

        $actor = User::factory()->verified()->create();
        $actor->assignRole(Role::Admin->value);
        $roleAssignmentCount = DB::table('model_has_roles')->count();
        Schema::drop('activity_log');
        self::actingAsSanctum($actor);

        $this->postJson('/api/v1/users', [
            'name' => 'Falha de auditoria',
            'email' => 'falha-auditoria@example.test',
            'cpf' => '12345678908',
            'password' => 'test-password-01',
            'active' => true,
            'roles' => [Role::User->value],
        ])->assertInternalServerError();

        $this->assertDatabaseMissing('users', ['email' => 'falha-auditoria@example.test']);
        $this->assertSame($roleAssignmentCount, DB::table('model_has_roles')->count());
        Notification::assertNothingSent();
    }

    public function test_admin_created_user_triggers_onboarding_invitation_with_signed_url_resend_and_expiration(): void
    {
        Notification::fake();

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo([Permission::UsersManage->value, Permission::UsersAssignRoles->value]);
        self::actingAsSanctum($actor);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Onboarding User',
            'email' => 'onboard@example.test',
            'cpf' => '12345678901',
            'password' => 'test-password-01',
            'active' => true,
            'roles' => [Role::User->value],
        ]);

        $response->assertCreated();
        $createdId = $response->json('data.id');
        $this->assertIsInt($createdId);

        $createdUser = User::findOrFail($createdId);
        $this->assertNull($createdUser->email_verified_at);

        Notification::assertSentTo($createdUser, VerifyEmail::class, static function (VerifyEmail $notification) use ($createdUser): bool {
            $mail = $notification->toMail($createdUser);

            return str_contains((string) $mail->actionUrl, 'verify-email')
                && str_contains((string) $mail->actionUrl, 'signature=');
        });

        Notification::fake();
        self::actingAsSanctum($createdUser);
        $this->postJson('/api/v1/email/verification-notification')
            ->assertStatus(202);
        Notification::assertSentTo($createdUser, VerifyEmail::class);

        $expiredUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinute(),
            ['id' => $createdUser->id, 'hash' => hash('sha256', $createdUser->email)]
        );
        $this->get($expiredUrl)->assertForbidden();
        $this->assertNull($createdUser->fresh()?->email_verified_at);

        $validUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHours(24),
            ['id' => $createdUser->id, 'hash' => hash('sha256', $createdUser->email)]
        );
        $this->get($validUrl)->assertRedirect();
        $this->assertNotNull($createdUser->fresh()?->email_verified_at);
    }

    public function test_admin_created_user_resend_is_throttled_with_429(): void
    {
        Notification::fake();

        $invited = User::factory()->unverified()->create(['email' => 'throttled@example.test']);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            self::actingAsSanctum($invited);
            $response = $this->postJson('/api/v1/email/verification-notification');

            if ($attempt < 4) {
                $response->assertStatus(202);

                continue;
            }

            $response->assertStatus(429)->assertHeader('Retry-After');
        }

        Notification::assertSentToTimes($invited, VerifyEmail::class, 3);
    }

    public function test_admin_created_user_invitation_link_expires_after_24_hours(): void
    {
        config(['app.frontend_url' => 'http://frontend.test']);

        $invited = User::factory()->unverified()->create(['email' => 'expiring@example.test']);

        $mail = (new VerifyEmail)->toMail($invited);
        $linkParts = parse_url((string) $mail->actionUrl);
        $this->assertIsArray($linkParts);
        parse_str((string) ($linkParts['query'] ?? ''), $query);
        $this->assertIsString($query['expires'] ?? null);
        $this->assertIsString($query['signature'] ?? null);

        $apiUrl = "/api/v1/email/verify/{$invited->id}/".hash('sha256', $invited->email).'?'
            .http_build_query(['expires' => $query['expires'], 'signature' => $query['signature']]);

        $this->travel(25)->hours();

        self::actingAsSanctum($invited);
        $this->getJson($apiUrl)->assertForbidden();

        $this->assertNull($invited->fresh()?->email_verified_at);
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

    public function test_lower_privilege_actor_cannot_change_privileged_target_email(): void
    {
        $target = User::factory()->verified()->create(['email' => 'protected-admin@example.test']);
        $target->assignRole(Role::Admin->value);
        $target->givePermissionTo(Permission::UsersUpdate->value);
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersUpdate->value);

        self::actingAsSanctum($actor);
        $this->patchJson("/api/v1/users/{$target->id}", [
            'email' => 'hijacked@example.test',
        ])->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'email' => 'protected-admin@example.test']);
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
        $actor->assignRole(Role::Admin->value);
        $actor->givePermissionTo(Permission::UsersAssignRoles->value);

        self::actingAsSanctum($actor);
        $response = $this->putJson("/api/v1/users/{$target->id}/roles", ['roles' => [Role::Admin->value]]);

        $response->assertOk()
            ->assertJsonPath('data.roles', [Role::Admin->value]);

        $this->assertTrue($target->refresh()->hasRole(Role::Admin->value));
        $this->assertFalse($target->hasRole(Role::User->value));

        $this->assertPrivilegedAudit($target, $actor);
    }

    public function test_assign_roles_persists_admin_and_user_then_replaces_only_submitted_roles(): void
    {
        SpatieRole::create(['name' => 'analyst', 'guard_name' => 'web']);

        $target = User::factory()->verified()->create();
        $actor = User::factory()->verified()->create();
        $actor->assignRole(Role::Admin->value);

        self::actingAsSanctum($actor);
        $this->putJson("/api/v1/users/{$target->id}/roles", [
            'roles' => [Role::Admin->value, Role::User->value],
        ])->assertOk()->assertJsonPath('data.roles', [Role::Admin->value, Role::User->value]);

        $this->assertTrue($target->refresh()->hasAllRoles([Role::Admin->value, Role::User->value]));

        self::actingAsSanctum($actor);
        $this->putJson("/api/v1/users/{$target->id}/roles", [
            'roles' => [Role::User->value, 'analyst'],
        ])->assertOk()->assertJsonPath('data.roles', ['analyst', Role::User->value]);

        $this->assertTrue($target->refresh()->hasAllRoles([Role::User->value, 'analyst']));
        $this->assertFalse($target->hasRole(Role::Admin->value));

        self::actingAsSanctum($actor);
        $this->putJson("/api/v1/users/{$target->id}/roles", ['roles' => ['analyst']])
            ->assertOk()
            ->assertJsonPath('data.roles', ['analyst']);

        $this->assertTrue($target->refresh()->hasRole('analyst'));
        $this->assertFalse($target->hasRole(Role::User->value));
    }

    public function test_assign_roles_denies_delegated_operator_escalating_to_admin(): void
    {
        $target = User::factory()->verified()->create();
        $target->assignRole(Role::User->value);

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersAssignRoles->value);

        self::actingAsSanctum($actor);
        $this->putJson("/api/v1/users/{$target->id}/roles", ['roles' => [Role::Admin->value]])
            ->assertStatus(403);

        $this->assertFalse($target->refresh()->hasRole(Role::Admin->value));
    }

    public function test_assign_roles_denies_delegated_operator_assigning_unheld_permissions(): void
    {
        $customRole = SpatieRole::create(['name' => 'manager', 'guard_name' => 'web']);
        $customRole->givePermissionTo(Permission::UsersManage->value);

        $target = User::factory()->verified()->create();
        $target->assignRole(Role::User->value);

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersAssignRoles->value);
        $actor->givePermissionTo(Permission::UsersView->value);

        self::actingAsSanctum($actor);
        $this->putJson("/api/v1/users/{$target->id}/roles", ['roles' => ['manager']])
            ->assertStatus(403);

        $this->assertFalse($target->refresh()->hasRole('manager'));
    }

    public function test_assign_roles_allows_delegated_operator_within_grant_ceiling(): void
    {
        $customRole = SpatieRole::create(['name' => 'viewer', 'guard_name' => 'web']);
        $customRole->givePermissionTo(Permission::UsersView->value);

        $target = User::factory()->verified()->create();
        $target->assignRole(Role::User->value);

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersAssignRoles->value);
        $actor->givePermissionTo(Permission::UsersView->value);

        self::actingAsSanctum($actor);
        $this->putJson("/api/v1/users/{$target->id}/roles", ['roles' => ['viewer']])
            ->assertOk()
            ->assertJsonPath('data.roles', ['viewer']);

        $this->assertTrue($target->refresh()->hasRole('viewer'));
    }

    public function test_assign_roles_denies_whole_list_when_one_role_exceeds_ceiling(): void
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

        self::actingAsSanctum($actor);
        $this->putJson("/api/v1/users/{$target->id}/roles", ['roles' => ['viewer', 'manager']])
            ->assertStatus(403);

        $fresh = $target->refresh();
        $this->assertFalse($fresh->hasRole('viewer'));
        $this->assertFalse($fresh->hasRole('manager'));
        $this->assertTrue($fresh->hasRole(Role::User->value));
    }

    public function test_assign_roles_applies_ceiling_to_permissions_inherited_via_role(): void
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

        self::actingAsSanctum($actor);
        $this->putJson("/api/v1/users/{$target->id}/roles", ['roles' => ['viewer']])
            ->assertOk()
            ->assertJsonPath('data.roles', ['viewer']);

        self::actingAsSanctum($actor);
        $this->putJson("/api/v1/users/{$target->id}/roles", ['roles' => ['manager']])
            ->assertStatus(403);

        $this->assertFalse($target->refresh()->hasRole('manager'));
    }

    public function test_assign_roles_allows_admin_to_grant_custom_role(): void
    {
        $manager = SpatieRole::create(['name' => 'manager', 'guard_name' => 'web']);
        $manager->givePermissionTo(Permission::UsersManage->value);
        $viewer = SpatieRole::create(['name' => 'viewer', 'guard_name' => 'web']);
        $viewer->givePermissionTo(Permission::UsersView->value);

        $target = User::factory()->verified()->create();
        $target->assignRole(Role::User->value);

        $actor = User::factory()->verified()->create();
        $actor->assignRole(Role::Admin->value);

        self::actingAsSanctum($actor);
        $this->putJson("/api/v1/users/{$target->id}/roles", ['roles' => ['manager']])
            ->assertOk()
            ->assertJsonPath('data.roles', ['manager']);

        $this->assertTrue($target->refresh()->hasRole('manager'));
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

        $this->putJson("/api/v1/users/{$target->id}/roles", ['roles' => [Role::User->value, Role::User->value]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('roles.1');

        $this->assertDatabaseMissing('model_has_roles', [
            'model_type' => User::class,
            'model_id' => $target->id,
        ]);
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

    public function test_assign_roles_rolls_back_when_audit_recording_fails(): void
    {
        $target = User::factory()->verified()->create();
        $target->assignRole(Role::User->value);

        $actor = User::factory()->verified()->create();
        $actor->assignRole(Role::Admin->value);

        Schema::drop('activity_log');

        self::actingAsSanctum($actor);
        $this->putJson("/api/v1/users/{$target->id}/roles", ['roles' => [Role::Admin->value]])
            ->assertStatus(500);

        $this->assertTrue($target->refresh()->hasRole(Role::User->value));
        $this->assertFalse($target->hasRole(Role::Admin->value));
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
