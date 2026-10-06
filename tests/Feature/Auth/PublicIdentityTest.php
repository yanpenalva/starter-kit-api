<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmail;
use App\Support\ApiMessage;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class PublicIdentityTest extends TestCase
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

    public function test_register_creates_unverified_user_and_queues_verification_without_authenticating(): void
    {
        Notification::fake();

        $response = $this->sessionHeaders()->postJson('/api/v1/register', [
            'name' => 'Ada Lovelace',
            'email' => '  Ada@Example.TEST ',
            'password' => 'correct-horse-12',
            'password_confirmation' => 'correct-horse-12',
        ]);

        $response->assertStatus(201);
        $userId = $response->json('id');
        $this->assertIsInt($userId);
        $response->assertJson([
            'id' => $userId,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'email_verified_at' => null,
        ]);
        $this->assertGuest('web');

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'email' => 'ada@example.test',
            'email_verified_at' => null,
            'deactivated_at' => null,
        ]);

        $user = User::findOrFail($userId);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_register_rejects_duplicate_email_case_insensitively_with_standard_422(): void
    {
        User::factory()->create(['email' => 'taken@example.test']);

        $response = $this->sessionHeaders()->postJson('/api/v1/register', [
            'name' => 'Clone',
            'email' => 'TAKEN@EXAMPLE.TEST',
            'password' => 'correct-horse-12',
            'password_confirmation' => 'correct-horse-12',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_register_validates_password_length_confirmation_and_cpf(): void
    {
        $response = $this->sessionHeaders()->postJson('/api/v1/register', [
            'name' => 'Short',
            'email' => 'short-pass@example.test',
            'password' => 'short-11-ch',
            'password_confirmation' => 'short-11-ch',
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors('password');

        $response = $this->sessionHeaders()->postJson('/api/v1/register', [
            'name' => 'Mismatch',
            'email' => 'mismatch@example.test',
            'password' => 'correct-horse-12',
            'password_confirmation' => 'different-horse-12',
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors('password');

        $response = $this->sessionHeaders()->postJson('/api/v1/register', [
            'name' => 'Bad Cpf',
            'email' => 'bad-cpf@example.test',
            'password' => 'correct-horse-12',
            'password_confirmation' => 'correct-horse-12',
            'cpf' => '11111111111',
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors('cpf');
    }

    public function test_register_without_session_returns_419(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'No Session',
            'email' => 'no-session@example.test',
            'password' => 'correct-horse-12',
            'password_confirmation' => 'correct-horse-12',
        ]);

        $response->assertStatus(419)->assertExactJson(['message' => ApiMessage::CSRF]);
    }

    public function test_register_throttle_returns_429(): void
    {
        $payload = ['name' => 'x', 'email' => 'bad', 'password' => 'x'];

        foreach (range(1, 6) as $attempt) {
            $blocked = $this->sessionHeaders()->postJson('/api/v1/register', $payload);
            $blocked->assertStatus($attempt < 6 ? 422 : 429);
        }

        $this->assertSame(ApiMessage::THROTTLED, $blocked->json('message'));
        $this->assertTrue($blocked->headers->has('Retry-After'));
    }

    public function test_resend_requires_authentication(): void
    {
        $this->postJson('/api/v1/email/verification-notification')
            ->assertStatus(401)
            ->assertExactJson(['message' => ApiMessage::UNAUTHENTICATED]);
    }

    public function test_resend_notifies_only_unverified_users_with_generic_202(): void
    {
        Notification::fake();

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified, 'web')
            ->postJson('/api/v1/email/verification-notification')
            ->assertStatus(202)
            ->assertExactJson(['message' => 'Se o email ainda não foi verificado, reenviamos o link.']);
        Notification::assertSentTo($unverified, VerifyEmail::class);

        $verified = User::factory()->create();
        $this->actingAs($verified, 'web')
            ->postJson('/api/v1/email/verification-notification')
            ->assertStatus(202)
            ->assertExactJson(['message' => 'Se o email ainda não foi verificado, reenviamos o link.']);
        Notification::assertNotSentTo($verified, VerifyEmail::class);
    }

    public function test_verify_marks_email_verified_dispatches_event_and_redirects_to_frontend(): void
    {
        Event::fake([Verified::class]);
        config(['app.frontend_url' => 'http://frontend.test']);

        $user = User::factory()->unverified()->create(['email' => 'verify-me@example.test']);
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHours(24),
            ['id' => $user->id, 'hash' => hash('sha256', $user->email)]
        );

        $response = $this->get($url);

        $response->assertStatus(302);
        $response->assertRedirect('http://frontend.test/verify-email?ok=1');
        $this->assertNotNull($user->fresh()?->email_verified_at);
        Event::assertDispatched(Verified::class, static function (Verified $event) use ($user): bool {
            return $event->user->getEmailForVerification() === $user->email;
        });
    }

    public function test_verify_with_tampered_hash_returns_403_and_keeps_user_unverified(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'tamper@example.test']);
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHours(24),
            ['id' => $user->id, 'hash' => hash('sha256', 'other@example.test')]
        );

        $this->get($url)->assertForbidden();
        $this->assertNull($user->fresh()?->email_verified_at);
    }

    public function test_verify_with_expired_link_returns_403(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'expired@example.test']);
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinute(),
            ['id' => $user->id, 'hash' => hash('sha256', $user->email)]
        );

        $this->get($url)->assertForbidden();
        $this->assertNull($user->fresh()?->email_verified_at);
    }

    public function test_verify_notification_link_verifies_the_user_end_to_end(): void
    {
        config(['app.frontend_url' => 'http://frontend.test']);

        $user = User::factory()->unverified()->create(['email' => 'link-check@example.test']);
        $notification = new VerifyEmail;
        $mail = $notification->toMail($user);

        $this->get($mail->actionUrl)->assertRedirect('http://frontend.test/verify-email?ok=1');
        $this->assertNotNull($user->fresh()?->email_verified_at);
    }
}
