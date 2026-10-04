<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LogoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $database = config('database.connections.pgsql.database');
        $this->assertIsString($database);
        $this->assertSame('starter_api_test', $database);
    }

    public function test_anonymous_logout_returns_401(): void
    {
        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(401)
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_authenticated_logout_invalidates_session_and_returns_204(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->withSession(['auth_data' => 'active_session'])
            ->postJson('/api/v1/logout');

        $response->assertNoContent();
        $this->assertSame('', $response->getContent());
        $this->assertGuest('web');
    }

    public function test_subsequent_profile_request_after_logout_returns_401(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/auth/my-profile')->assertOk();

        $this->postJson('/api/v1/logout')->assertNoContent();

        // Forget cached guard instances in test container so subsequent request reads fresh state
        $this->app['auth']->forgetGuards();

        $profileResponse = $this->getJson('/api/v1/auth/my-profile');
        $profileResponse->assertStatus(401);
    }

    public function test_deactivated_account_with_existing_session_can_logout_and_terminate_session(): void
    {
        $user = User::factory()->deactivated()->create();

        $response = $this->actingAs($user, 'web')
            ->postJson('/api/v1/logout');

        $response->assertNoContent();
        $this->assertGuest('web');
    }
}
