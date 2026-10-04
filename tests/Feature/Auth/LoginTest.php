<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $database = config('database.connections.pgsql.database');
        $this->assertIsString($database);
        $this->assertSame('starter_api_test', $database);

        RateLimiter::clear(hash('sha256', 'user@example.test|127.0.0.1'));
    }

    public function test_successful_login_returns_204_and_regenerates_session(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.test',
            'password' => 'secret123',
        ]);

        $response = $this->withSession(['previous' => 'value'])
            ->withHeaders([
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ])
            ->postJson('/api/v1/login', [
                'email' => 'user@example.test',
                'password' => 'secret123',
            ]);

        $response->assertNoContent();
        $this->assertSame('', $response->getContent());
        $this->assertAuthenticatedAs($user, 'web');

    }

    public function test_login_is_case_insensitive_and_whitespace_trimmed(): void
    {
        $user = User::factory()->create([
            'email' => 'case.sensitive@example.test',
            'password' => 'secret123',
        ]);

        $response = $this->withSession([])
            ->withHeaders([
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ])
            ->postJson('/api/v1/login', [
                'email' => '  CASE.SENSITIVE@EXAMPLE.TEST  ',
                'password' => 'secret123',
            ]);

        $response->assertNoContent();
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_unknown_email_returns_generic_401(): void
    {
        $response = $this->withSession([])
            ->withHeaders([
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ])
            ->postJson('/api/v1/login', [
                'email' => 'unknown@example.test',
                'password' => 'secret123',
            ]);

        $response->assertStatus(401)
            ->assertExactJson(['message' => 'Invalid credentials.']);
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_wrong_password_returns_generic_401(): void
    {
        User::factory()->create([
            'email' => 'user@example.test',
            'password' => 'secret123',
        ]);

        $response = $this->withSession([])
            ->withHeaders([
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ])
            ->postJson('/api/v1/login', [
                'email' => 'user@example.test',
                'password' => 'wrong-password',
            ]);

        $response->assertStatus(401)
            ->assertExactJson(['message' => 'Invalid credentials.']);
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_deactivated_account_with_wrong_password_returns_generic_401_preventing_enumeration(): void
    {
        User::factory()->deactivated()->create([
            'email' => 'deactivated@example.test',
            'password' => 'secret123',
        ]);

        $response = $this->withSession([])
            ->withHeaders([
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ])
            ->postJson('/api/v1/login', [
                'email' => 'deactivated@example.test',
                'password' => 'wrong-password',
            ]);

        $response->assertStatus(401)
            ->assertExactJson(['message' => 'Invalid credentials.']);
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_deactivated_account_with_correct_password_returns_403(): void
    {
        User::factory()->deactivated()->create([
            'email' => 'deactivated@example.test',
            'password' => 'secret123',
        ]);

        $response = $this->withSession([])
            ->withHeaders([
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ])
            ->postJson('/api/v1/login', [
                'email' => 'deactivated@example.test',
                'password' => 'secret123',
            ]);

        $response->assertStatus(403)
            ->assertExactJson(['message' => 'This account has been deactivated.']);
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_unverified_account_can_login_with_active_session(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'unverified@example.test',
            'password' => 'secret123',
        ]);

        $response = $this->withSession([])
            ->withHeaders([
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ])
            ->postJson('/api/v1/login', [
                'email' => 'unverified@example.test',
                'password' => 'secret123',
            ]);

        $response->assertNoContent();
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_validation_errors_return_422_without_echoing_credentials(): void
    {
        $response = $this->withSession([])
            ->withHeaders([
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ])
            ->postJson('/api/v1/login', [
                'email' => 'not-an-email',
                'password' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);

        $content = (string) $response->getContent();
        $this->assertStringNotContainsString('password123', $content);
    }

    public function test_login_rate_limiting_enforces_5_attempts_per_minute_with_retry_after(): void
    {
        $email = 'throttled@example.test';
        $normalizedKey = hash('sha256', $email.'|127.0.0.1');
        RateLimiter::clear($normalizedKey);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this->withSession([])
                ->withHeaders([
                    'Origin' => 'http://localhost:5173',
                    'Referer' => 'http://localhost:5173/',
                ])
                ->postJson('/api/v1/login', [
                    'email' => $attempt % 2 === 0 ? '  THROTTLED@example.test  ' : $email,
                    'password' => 'wrong',
                ]);

            $response->assertStatus(401);
        }

        // 6th attempt should be blocked with 429
        $blocked = $this->withSession([])
            ->withHeaders([
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ])
            ->postJson('/api/v1/login', [
                'email' => '  throttled@EXAMPLE.TEST  ',
                'password' => 'wrong',
            ]);

        $blocked->assertStatus(429);
        $this->assertTrue($blocked->headers->has('Retry-After'));

        // Different email on the same IP is not blocked
        $differentIdentity = $this->withSession([])
            ->withHeaders([
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ])
            ->postJson('/api/v1/login', [
                'email' => 'other@example.test',
                'password' => 'wrong',
            ]);
        $differentIdentity->assertStatus(401);

        RateLimiter::clear($normalizedKey);
    }

    public function test_bypassing_stateful_session_is_rejected_with_419(): void
    {
        // No session started (simulating missing stateful pipeline or non-stateful client)
        $response = $this->postJson('/api/v1/login', [
            'email' => 'user@example.test',
            'password' => 'secret123',
        ]);

        $response->assertStatus(419);
    }
}
