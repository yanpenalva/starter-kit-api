<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\LoginController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.default');
        $this->assertContains($connection, ['pgsql', 'sqlite']);

        if ($connection === 'pgsql') {
            $database = config('database.connections.pgsql.database');
            $this->assertIsString($database);
            $this->assertSame($database, DB::connection()->getDatabaseName());
            $this->assertSame('starter_api_test', $database);
        }

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

    public function test_successful_login_rehashes_a_legacy_bcrypt_password_using_argon2id(): void
    {
        $this->assertSame('argon2id', config('hashing.driver'));

        $legacyHash = password_hash('secret123', PASSWORD_BCRYPT, ['cost' => 4]);
        $user = User::factory()->create([
            'email' => 'legacy@example.test',
            'password' => 'secret123',
        ]);
        DB::table('users')->where('id', $user->getKey())->update(['password' => $legacyHash]);

        $response = $this->withSession([])
            ->withHeaders([
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ])
            ->postJson('/api/v1/login', [
                'email' => 'legacy@example.test',
                'password' => 'secret123',
            ]);

        $response->assertNoContent();
        $user->refresh();
        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertSame('argon2id', password_get_info($user->password)['algoName']);
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
                'email' => "\t  CASE.SENSITIVE@EXAMPLE.TEST \r\n",
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
            ->assertExactJson(['message' => 'Credenciais inválidas.']);
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
            ->assertExactJson(['message' => 'Credenciais inválidas.']);
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
            ->assertExactJson(['message' => 'Credenciais inválidas.']);
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_deactivated_account_with_correct_password_returns_403(): void
    {
        $verifiedAt = now()->floorSecond()->subDay();
        User::factory()->create([
            'email' => 'deactivated@example.test',
            'password' => 'secret123',
            'email_verified_at' => $verifiedAt,
            'deactivated_at' => now()->floorSecond(),
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
            ->assertJson([
                'message' => 'Esta conta foi desativada.',
                'reason' => 'suspended',
                'email_verified_at' => $verifiedAt->clone()->utc()->format('Y-m-d\TH:i:s\Z'),
            ]);
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
            ->assertJsonValidationErrors(['email', 'password'])
            ->assertJsonStructure(['message', 'errors']);

        $this->assertSame('O campo email deve ser um endereço de e-mail válido.', $response->json('errors.email.0'));
        $this->assertSame('O campo password é obrigatório.', $response->json('errors.password.0'));

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
        $blocked->assertExactJson(['message' => 'Muitas tentativas. Por favor, tente novamente mais tarde.']);

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

    public function test_login_rate_limiting_caps_distinct_emails_per_ip(): void
    {
        $ipKey = 'login-ip|127.0.0.1';
        RateLimiter::clear($ipKey);

        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $response = $this->withSession([])
                ->withHeaders([
                    'Origin' => 'http://localhost:5173',
                    'Referer' => 'http://localhost:5173/',
                ])
                ->postJson('/api/v1/login', [
                    'email' => "identity-{$attempt}@example.test",
                    'password' => 'wrong',
                ]);

            $response->assertStatus(401);
        }

        $blocked = $this->withSession([])
            ->withHeaders([
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ])
            ->postJson('/api/v1/login', [
                'email' => 'twenty-first@example.test',
                'password' => 'wrong',
            ]);

        $blocked->assertStatus(429);
        $this->assertTrue($blocked->headers->has('Retry-After'));
        RateLimiter::clear($ipKey);
    }

    public function test_bypassing_stateful_session_is_rejected_with_419(): void
    {

        $response = $this->postJson('/api/v1/login', [
            'email' => 'user@example.test',
            'password' => 'secret123',
        ]);

        $response->assertStatus(419)
            ->assertExactJson(['message' => 'Token CSRF inválido ou expirado.']);
    }

    public function test_login_rejects_bearer_authorization_header_with_401(): void
    {
        $response = $this->withSession([])
            ->withHeaders([
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
                'Authorization' => 'Bearer some-bearer-token',
            ])
            ->postJson('/api/v1/login', [
                'email' => 'user@example.test',
                'password' => 'secret123',
            ]);

        $response->assertStatus(401)
            ->assertExactJson(['message' => 'Não autenticado.']);
    }

    public function test_login_succeeds_for_user_with_stored_whitespace_in_database(): void
    {
        $user = User::factory()->create([
            'email' => "  stored.whitespace@example.test\t",
            'password' => 'secret123',
        ]);

        $response = $this->withSession([])
            ->withHeaders([
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ])
            ->postJson('/api/v1/login', [
                'email' => 'stored.whitespace@example.test',
                'password' => 'secret123',
            ]);

        $response->assertNoContent();
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_dummy_hash_matches_the_current_hashing_policy(): void
    {
        $dummy = (new \ReflectionClass(LoginController::class))->getConstant('DUMMY_HASH');
        $this->assertIsString($dummy);

        $this->assertFalse(Hash::needsRehash($dummy));
    }
}
