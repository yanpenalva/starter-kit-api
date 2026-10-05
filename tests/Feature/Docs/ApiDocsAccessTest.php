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

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('pgsql', config('database.default'));
        // CI usa 127.0.0.1; Compose usa o nome do serviço.
        $databaseHost = config('database.connections.pgsql.host');
        $this->assertIsString($databaseHost);
        $this->assertNotSame('', $databaseHost);
        $this->assertSame('5432', config('database.connections.pgsql.port'));
        $this->assertSame('starter_api_test', config('database.connections.pgsql.database'));
        $this->assertSame('starter_api_test', config('database.connections.pgsql.username'));
        $this->assertNull(config('database.connections.pgsql.url'));
        $mailMailer = config('mail.default');
        $this->assertContains($mailMailer, ['log', 'smtp']);
        // CI usa loopback; Compose usa nome interno do serviço.
        $this->assertContains(config('mail.mailers.smtp.host'), ['127.0.0.1', 'mailpit-test']);
        $this->assertSame('1025', config('mail.mailers.smtp.port'));
        $this->assertSame('testing', app()->environment());
        $this->assertFalse(config('app.debug'));
    }

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_production_docs_policy_denies_guest_non_admin_unverified_and_deactivated_users(): void
    {
        $this->useProductionDocsPolicy();

        $this->assertDocsForbiddenFor(null);

        /** @var User $nonAdmin */
        $nonAdmin = User::factory()->create([
            'email_verified_at' => now(),
            'deactivated_at' => null,
        ]);
        $nonAdmin->assignRole(Role::User->value);
        $this->assertDocsForbiddenFor($nonAdmin);

        /** @var User $unverifiedAdmin */
        $unverifiedAdmin = User::factory()->create([
            'email_verified_at' => null,
            'deactivated_at' => null,
        ]);
        $unverifiedAdmin->assignRole(Role::Admin->value);
        $this->assertDocsForbiddenFor($unverifiedAdmin);

        /** @var User $deactivatedAdmin */
        $deactivatedAdmin = User::factory()->create([
            'email_verified_at' => now(),
            'deactivated_at' => now(),
        ]);
        $deactivatedAdmin->assignRole(Role::Admin->value);
        $this->assertDocsForbiddenFor($deactivatedAdmin);
    }

    public function test_production_docs_policy_allows_verified_active_admin_for_ui_and_json(): void
    {
        $this->useProductionDocsPolicy();

        /** @var User $admin */
        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'deactivated_at' => null,
        ]);
        $admin->assignRole(Role::Admin->value);

        $this->actingAs($admin, 'web');

        $document = $this->getJson('/docs/api.json');
        $document->assertOk();
        $this->assertSame('3.1.0', $document->json('openapi'));

        $ui = $this->get('/docs/api');
        $ui->assertOk();
        $this->assertStringStartsWith('text/html', (string) $ui->headers->get('content-type'));
        $this->assertNotSame('', $ui->getContent());
    }

    private function useProductionDocsPolicy(): void
    {
        $this->assertSame('testing', app()->environment());

        $this->app->detectEnvironment(static fn (): string => 'production');
        config(['app.debug' => false]);

        $this->assertSame('production', app()->environment());
        $this->assertFalse(config('app.debug'));
    }

    private function assertDocsForbiddenFor(?User $user): void
    {
        $this->app['auth']->forgetGuards();

        if ($user instanceof User) {
            $this->actingAs($user, 'web');
        }

        $responses = [
            $this->get('/docs/api'),
            $this->getJson('/docs/api.json'),
        ];

        foreach ($responses as $response) {
            $response->assertForbidden();
            $body = (string) $response->getContent();
            $this->assertStringNotContainsString('Stack trace', $body);
            $this->assertStringNotContainsString('Illuminate\\', $body);
            $this->assertStringNotContainsString('SQLSTATE[', $body);
        }

        $this->app['auth']->forgetGuards();
    }
}
