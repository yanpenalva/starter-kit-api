<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPassword as ResetPasswordNotification;
use App\Support\ApiMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function sessionHeaders(): static
    {
        return $this->withSession([])
            ->withHeaders([
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ]);
    }

    public function test_forgot_password_always_returns_generic_202_without_enumerating_accounts(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'reset-known@example.test']);

        foreach (['reset-known@example.test', 'reset-unknown@example.test'] as $email) {
            $this->sessionHeaders()->postJson('/api/v1/forgot-password', ['email' => $email])
                ->assertStatus(202)
                ->assertExactJson(['message' => 'Se o email existir, enviamos o link.']);
        }

        Notification::assertSentTo(
            User::where('email', 'reset-known@example.test')->firstOrFail(),
            ResetPasswordNotification::class
        );
        Notification::assertSentTimes(ResetPasswordNotification::class, 1);
    }

    public function test_forgot_password_is_case_insensitive_and_skips_deactivated_accounts(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'reset-case@example.test']);
        $deactivated = User::factory()->deactivated()->create(['email' => 'reset-off@example.test']);

        $this->sessionHeaders()->postJson('/api/v1/forgot-password', ['email' => '  RESET-CASE@EXAMPLE.TEST '])
            ->assertStatus(202);

        Notification::assertSentTo(
            User::where('email', 'reset-case@example.test')->firstOrFail(),
            ResetPasswordNotification::class
        );
        Notification::assertNotSentTo($deactivated, ResetPasswordNotification::class);
    }

    public function test_forgot_password_throttle_returns_429(): void
    {
        foreach (range(1, 4) as $attempt) {
            $blocked = $this->sessionHeaders()->postJson('/api/v1/forgot-password', ['email' => 'throttle-me@example.test']);
            $blocked->assertStatus($attempt < 4 ? 202 : 429);
        }

        $this->assertSame(ApiMessage::THROTTLED, $blocked->json('message'));
    }

    public function test_reset_password_with_valid_token_updates_password_and_invalidates_token(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset-flow@example.test']);

        $this->sessionHeaders()->postJson('/api/v1/forgot-password', ['email' => 'reset-flow@example.test'])
            ->assertStatus(202);

        /** @var ResetPasswordNotification $sent */
        $sent = Notification::sent($user, ResetPasswordNotification::class)->first();
        $this->assertInstanceOf(ResetPasswordNotification::class, $sent);

        $this->sessionHeaders()->postJson('/api/v1/reset-password', [
            'email' => 'reset-flow@example.test',
            'token' => $sent->token,
            'password' => 'brand-new-password-12',
            'password_confirmation' => 'brand-new-password-12',
        ])->assertOk()->assertExactJson(['message' => 'Sua senha foi redefinida.']);

        $this->assertIsString($freshPassword = $user->fresh()?->password);
        $this->assertTrue(Hash::check('brand-new-password-12', $freshPassword));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'reset-flow@example.test']);

        $this->sessionHeaders()->postJson('/api/v1/reset-password', [
            'email' => 'reset-flow@example.test',
            'token' => $sent->token,
            'password' => 'another-password-12',
            'password_confirmation' => 'another-password-12',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_reset_password_with_invalid_token_or_unknown_email_returns_422(): void
    {
        User::factory()->create(['email' => 'reset-bad@example.test']);

        $invalidToken = $this->sessionHeaders()->postJson('/api/v1/reset-password', [
            'email' => 'reset-bad@example.test',
            'token' => 'invalid-token',
            'password' => 'brand-new-password-12',
            'password_confirmation' => 'brand-new-password-12',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $unknownUser = $this->sessionHeaders()->postJson('/api/v1/reset-password', [
            'email' => 'reset-ghost@example.test',
            'token' => 'invalid-token',
            'password' => 'brand-new-password-12',
            'password_confirmation' => 'brand-new-password-12',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertSame($invalidToken->json('errors.email.0'), $unknownUser->json('errors.email.0'));
    }

    public function test_reset_password_throttle_returns_429(): void
    {
        $payload = [
            'email' => 'reset-throttled@example.test',
            'token' => 'invalid-token',
            'password' => 'brand-new-password-12',
            'password_confirmation' => 'brand-new-password-12',
        ];

        foreach (range(1, 6) as $attempt) {
            $blocked = $this->sessionHeaders()->postJson('/api/v1/reset-password', $payload);
            $blocked->assertStatus($attempt < 6 ? 422 : 429);
        }

        $this->assertSame(ApiMessage::THROTTLED, $blocked->json('message'));
    }
}
