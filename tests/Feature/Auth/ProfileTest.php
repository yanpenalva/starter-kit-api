<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $database = config('database.connections.pgsql.database');
        $this->assertIsString($database);
        $this->assertSame('starter_api_test', $database);

        $this->seed(RbacCatalogueSeeder::class);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/auth/my-profile');

        $response->assertStatus(401)
            ->assertExactJson(['message' => 'Unauthenticated.']);
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

        $response = $this->actingAs($user, 'web')
            ->getJson('/api/v1/auth/my-profile');

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

        // Roles and permissions are sorted lists
        $roles = $data['roles'];
        $this->assertIsArray($roles);
        $this->assertContains(Role::User->value, $roles);

        $permissions = $data['permissions'];
        $this->assertIsArray($permissions);
        $this->assertContains(Permission::UsersView->value, $permissions);

        // Sensitive and unapproved fields are absent
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

        // Even if accidentally assigned admin role and direct permissions in DB
        $user->assignRole(Role::Admin->value);
        $user->givePermissionTo(Permission::UsersAssignRoles->value);

        $response = $this->actingAs($user, 'web')
            ->getJson('/api/v1/auth/my-profile');

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
        $user = User::factory()->deactivated()->create([
            'email' => 'deactivated@example.test',
        ]);

        $response = $this->actingAs($user, 'web')
            ->getJson('/api/v1/auth/my-profile');

        $response->assertStatus(403)
            ->assertExactJson(['message' => 'This account has been deactivated.']);
    }

    public function test_verified_deactivated_account_remains_blocked_with_403(): void
    {
        $user = User::factory()->create([
            'email' => 'verified.deactivated@example.test',
            'email_verified_at' => now(),
            'deactivated_at' => now(),
        ]);

        $response = $this->actingAs($user, 'web')
            ->getJson('/api/v1/auth/my-profile');

        $response->assertStatus(403)
            ->assertExactJson(['message' => 'This account has been deactivated.']);
    }

    public function test_profile_returns_strictly_authenticated_user_representation(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        $response = $this->actingAs($alice, 'web')
            ->getJson('/api/v1/auth/my-profile?user_id='.$bob->id);

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
            ->assertExactJson(['message' => 'Unauthenticated.']);

        $this->assertSame([], $executedQueries);
    }

    public function test_bearer_authorization_header_is_rejected_even_when_session_is_authenticated(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->withHeader('Authorization', 'Bearer dummy-token')
            ->getJson('/api/v1/auth/my-profile');

        $response->assertStatus(401)
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }
}
