<?php

declare(strict_types=1);

namespace Tests\Feature\Docs;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApiDocsAccessTest extends TestCase
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

    public function test_docs_json_denied_for_guests(): void
    {
        $response = $this->getJson('/docs/api.json');

        $response->assertForbidden();
    }

    public function test_docs_ui_denied_for_guests(): void
    {
        $response = $this->get('/docs/api');

        $response->assertForbidden();
    }

    public function test_docs_denied_for_non_admin_users(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'deactivated_at' => null,
        ]);
        $user->assignRole(Role::User->value);

        $this->actingAs($user, 'web')
            ->getJson('/docs/api.json')
            ->assertForbidden();
    }

    public function test_docs_denied_for_unverified_admins(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'email_verified_at' => null,
            'deactivated_at' => null,
        ]);
        $user->assignRole(Role::Admin->value);

        $this->actingAs($user, 'web')
            ->getJson('/docs/api.json')
            ->assertForbidden();
    }

    public function test_docs_denied_for_deactivated_admins(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'deactivated_at' => now(),
        ]);
        $user->assignRole(Role::Admin->value);

        $this->actingAs($user, 'web')
            ->getJson('/docs/api.json')
            ->assertForbidden();
    }

    public function test_docs_allowed_for_verified_active_admins(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'deactivated_at' => null,
        ]);
        $user->assignRole(Role::Admin->value);

        $response = $this->actingAs($user, 'web')
            ->getJson('/docs/api.json');

        $response->assertOk();
        $document = $response->json();
        $this->assertIsArray($document);
        $this->assertSame('3.1.0', $document['openapi']);
    }
}
