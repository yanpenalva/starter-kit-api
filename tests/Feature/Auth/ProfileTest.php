<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\VerifyEmail;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concern\ActsAsSanctumUser;
use Tests\TestCase;

final class ProfileTest extends TestCase
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

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/auth/my-profile');

        $response->assertStatus(401)
            ->assertExactJson(['message' => 'Não autenticado.']);
    }

    public function test_verified_active_user_returns_full_profile_with_roles_and_permissions(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'verified@example.test',
            'email_verified_at' => now(),
            'deactivated_at' => null,
            'cpf' => '52998224725',
        ]);

        $user->assignRole(Role::User->value);
        $user->givePermissionTo(Permission::UsersView->value);

        self::actingAsSanctum(User::query()->whereKey($user->getKey())->firstOrFail());
        $response = $this->getJson('/api/v1/auth/my-profile');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'email_verified_at',
                    'roles',
                    'permissions',
                ],
            ]);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertSame($user->id, $data['id']);
        $this->assertSame($user->name, $data['name']);
        $this->assertSame('verified@example.test', $data['email']);
        $emailVerifiedAt = $data['email_verified_at'];
        $this->assertIsString($emailVerifiedAt);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $emailVerifiedAt);

        $roles = $data['roles'];
        $this->assertIsArray($roles);
        $this->assertContains(Role::User->value, $roles);

        $permissions = $data['permissions'];
        $this->assertIsArray($permissions);
        $this->assertContains(Permission::UsersView->value, $permissions);

        $this->assertArrayNotHasKey('cpf', $data);
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('remember_token', $data);
    }

    public function test_unverified_user_returns_minimal_profile_with_strictly_empty_roles_and_permissions(): void
    {
        /** @var User $user */
        $user = User::factory()->unverified()->create([
            'email' => 'unverified@example.test',
            'deactivated_at' => null,
        ]);

        $user->assignRole(Role::Admin->value);
        $user->givePermissionTo(Permission::UsersAssignRoles->value);

        self::actingAsSanctum(User::query()->whereKey($user->getKey())->firstOrFail());
        $response = $this->getJson('/api/v1/auth/my-profile');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertSame($user->id, $data['id']);
        $this->assertSame('unverified@example.test', $data['email']);
        $this->assertNull($data['email_verified_at']);
        $this->assertSame([], $data['roles']);
        $this->assertSame([], $data['permissions']);
    }

    public function test_deactivated_account_with_existing_session_returns_403(): void
    {
        $verifiedAt = now()->floorSecond()->subDay();
        $user = User::factory()->create([
            'email' => 'deactivated@example.test',
            'email_verified_at' => $verifiedAt,
            'deactivated_at' => now()->floorSecond(),
        ]);

        self::actingAsSanctum(User::query()->whereKey($user->getKey())->firstOrFail());
        $response = $this->getJson('/api/v1/auth/my-profile');

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Esta conta foi desativada.',
                'reason' => 'suspended',
                'email_verified_at' => $verifiedAt->clone()->utc()->format('Y-m-d\TH:i:s\Z'),
            ]);
    }

    public function test_verified_deactivated_account_remains_blocked_with_403(): void
    {
        $verifiedAt = now()->floorSecond();
        $user = User::factory()->create([
            'email' => 'verified.deactivated@example.test',
            'email_verified_at' => $verifiedAt,
            'deactivated_at' => $verifiedAt->clone()->subDay(),
        ]);

        self::actingAsSanctum(User::query()->whereKey($user->getKey())->firstOrFail());
        $response = $this->getJson('/api/v1/auth/my-profile');

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Esta conta foi desativada.',
                'reason' => 'pending_approval',
                'email_verified_at' => $verifiedAt->clone()->utc()->format('Y-m-d\TH:i:s\Z'),
            ]);
    }

    public function test_pending_unverified_account_reads_own_profile_for_limited_session(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'pending.profile@example.test',
            'deactivated_at' => now(),
        ]);

        self::actingAsSanctum(User::query()->whereKey($user->getKey())->firstOrFail());
        $response = $this->getJson('/api/v1/auth/my-profile');

        $response->assertOk()
            ->assertJsonPath('data.email', 'pending.profile@example.test')
            ->assertJsonPath('data.email_verified_at', null);
    }

    public function test_profile_returns_strictly_authenticated_user_representation(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        self::actingAsSanctum(User::query()->whereKey($alice->getKey())->firstOrFail());
        $response = $this->getJson('/api/v1/auth/my-profile?user_id='.$bob->id);

        $response->assertOk();
        $this->assertSame($alice->id, $response->json('data.id'));
        $this->assertSame('Alice', $response->json('data.name'));
    }

    public function test_bearer_authorization_header_returns_401_without_querying_tokens(): void
    {
        $executedQueries = [];
        DB::listen(static function (QueryExecuted $query) use (&$executedQueries): void {
            $executedQueries[] = $query->sql;
        });

        $response = $this->withHeader('Authorization', 'Bearer invalid-or-dummy-token')
            ->getJson('/api/v1/auth/my-profile');

        $response->assertStatus(401)
            ->assertExactJson(['message' => 'Não autenticado.']);

        $this->assertSame([], $executedQueries);
    }

    public function test_bearer_authorization_header_is_rejected_even_when_session_is_authenticated(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        self::actingAsSanctum(User::query()->whereKey($user->getKey())->firstOrFail());
        $response = $this->withHeader('Authorization', 'Bearer dummy-token')
            ->getJson('/api/v1/auth/my-profile');

        $response->assertStatus(401)
            ->assertExactJson(['message' => 'Não autenticado.']);
    }

    public function test_owner_edits_name_without_admin_permissions(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'selfservice@example.test',
        ]);
        $user->assignRole(Role::User->value);

        self::actingAsSanctum($user->fresh() ?? $user);

        $this->patchJson('/api/v1/auth/my-profile', ['name' => 'Updated Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertSame('Updated Name', $user->fresh()?->name);
    }

    public function test_owner_changes_email_resets_verification_and_dispatches_notification(): void
    {
        Notification::fake();

        $user = User::factory()->verified()->create(['email' => 'old-email@example.test']);
        $user->assignRole(Role::User->value);

        self::actingAsSanctum($user->fresh() ?? $user);

        $this->patchJson('/api/v1/auth/my-profile', ['email' => 'new-email@example.test'])
            ->assertOk()
            ->assertJsonPath('data.email', 'new-email@example.test')
            ->assertJsonPath('data.email_verified_at', null);

        $fresh = $user->fresh();
        $this->assertInstanceOf(User::class, $fresh);
        $this->assertSame('new-email@example.test', $fresh->email);
        $this->assertNull($fresh->email_verified_at);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_owner_updating_same_email_does_not_reset_verification(): void
    {
        Notification::fake();

        $user = User::factory()->verified()->create(['email' => 'keep-email@example.test']);
        $user->assignRole(Role::User->value);

        self::actingAsSanctum($user->fresh() ?? $user);

        $this->patchJson('/api/v1/auth/my-profile', [
            'email' => 'keep-email@example.test',
            'name' => 'Renamed Only',
        ])->assertOk();

        $this->assertNotNull($user->fresh()?->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_owner_cannot_change_email_to_another_users_email(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'taken@example.test']);
        $user = User::factory()->verified()->create(['email' => 'mine@example.test']);
        $user->assignRole(Role::User->value);

        self::actingAsSanctum($user->fresh() ?? $user);

        $this->patchJson('/api/v1/auth/my-profile', ['email' => 'Taken@Example.test'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $fresh = $user->fresh();
        $this->assertInstanceOf(User::class, $fresh);
        $this->assertSame('mine@example.test', $fresh->email);
        $this->assertNotNull($fresh->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_owner_cannot_set_email_verified_at_via_payload(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'unverified@example.test',
            'email_verified_at' => null,
        ]);
        $user->assignRole(Role::User->value);

        self::actingAsSanctum($user->fresh() ?? $user);

        $this->patchJson('/api/v1/auth/my-profile', [
            'name' => 'Still Unverified',
            'email_verified_at' => now()->toIso8601String(),
        ])->assertOk();

        $this->assertNull($user->fresh()?->email_verified_at);

        $this->patchJson('/api/v1/auth/my-profile', [
            'email' => 'changed@example.test',
            'email_verified_at' => now()->toIso8601String(),
        ])->assertOk()
            ->assertJsonPath('data.email_verified_at', null);

        $this->assertNull($user->fresh()?->email_verified_at);
    }

    public function test_owner_changes_password_requires_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password-123')]);
        $user->assignRole(Role::User->value);

        self::actingAsSanctum($user->fresh() ?? $user);

        $this->putJson('/api/v1/auth/password', [
            'current_password' => 'wrong-password-123',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $afterFailure = $user->fresh();
        $this->assertInstanceOf(User::class, $afterFailure);
        $this->assertTrue(Hash::check('old-password-123', $afterFailure->password));
        $this->assertFalse(Hash::check('new-password-123', $afterFailure->password));

        $this->putJson('/api/v1/auth/password', [
            'current_password' => 'old-password-123',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password-123', (string) $user->fresh()?->password));
    }

    public function test_owner_can_change_password_via_profile_patch_requiring_current_password(): void
    {
        $newPassword = 'fake-profile-password-123';
        $user = User::factory()->create(['password' => Hash::make('old-password-123')]);

        self::actingAsSanctum($user->fresh() ?? $user);

        $this->patchJson('/api/v1/auth/my-profile', [
            'current_password' => 'wrong-pass',
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $afterFailure = $user->fresh();
        $this->assertInstanceOf(User::class, $afterFailure);
        $this->assertTrue(Hash::check('old-password-123', $afterFailure->password));

        $this->patchJson('/api/v1/auth/my-profile', [
            'current_password' => 'old-password-123',
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertOk();

        $this->assertTrue(Hash::check($newPassword, (string) $user->fresh()?->password));
    }

    public function test_owner_cannot_modify_privileged_fields_via_self_service(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::User->value);

        self::actingAsSanctum($user->fresh() ?? $user);

        $this->patchJson('/api/v1/auth/my-profile', [
            'name' => 'Valid Name',
            'roles' => [Role::Admin->value],
            'active' => false,
        ])->assertOk();

        $fresh = $user->fresh();
        $this->assertInstanceOf(User::class, $fresh);
        $this->assertFalse($fresh->hasRole(Role::Admin->value));
        $this->assertNull($fresh->deactivated_at);
    }

    public function test_unauthenticated_profile_update_is_rejected(): void
    {
        $this->patchJson('/api/v1/auth/my-profile', ['name' => 'Anonymous'])
            ->assertStatus(401)
            ->assertExactJson(['message' => 'Não autenticado.']);
    }

    public function test_unauthenticated_password_update_is_rejected(): void
    {
        $this->putJson('/api/v1/auth/password', [
            'current_password' => 'whatever-password-123',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertStatus(401)
            ->assertExactJson(['message' => 'Não autenticado.']);
    }
}
