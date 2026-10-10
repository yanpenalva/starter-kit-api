<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmail;
use App\Support\ApiMessage;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use PDO;
use Tests\Concern\ActsAsSanctumUser;
use Tests\TestCase;

final class PublicIdentityTest extends TestCase
{
    use ActsAsSanctumUser;
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

        $registered = User::findOrFail($userId);
        $this->assertNotNull($registered->deactivated_at);
        $this->assertFalse($registered->hasAnyRole());
        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'email' => 'ada@example.test',
            'email_verified_at' => null,
        ]);

        $user = User::findOrFail($userId);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_register_ignores_active_and_roles_and_does_not_send_disabled_accounts_to_login(): void
    {
        Notification::fake();

        $response = $this->sessionHeaders()->postJson('/api/v1/register', [
            'name' => 'Pending Approval',
            'email' => 'pending-approval@example.test',
            'password' => 'correct-horse-12',
            'password_confirmation' => 'correct-horse-12',
            'active' => true,
            'roles' => ['admin'],
        ]);

        $response->assertCreated();
        $user = User::query()->where('email', 'pending-approval@example.test')->firstOrFail();
        $this->assertNotNull($user->deactivated_at);
        $this->assertFalse($user->hasAnyRole());

        $this->sessionHeaders()->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'correct-horse-12',
        ])->assertStatus(403);
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

    public function test_register_returns_422_when_email_is_inserted_concurrently(): void
    {
        $email = 'race-'.Str::uuid()->toString().'@example.test';
        $inserted = false;
        User::creating(static function (User $user) use ($email, &$inserted): void {
            if ($inserted) {
                return;
            }

            $inserted = true;
            self::insertRaceRow(['email' => $email]);
        });

        $response = $this->sessionHeaders()->postJson('/api/v1/register', [
            'name' => 'Race',
            'email' => $email,
            'password' => 'correct-horse-12',
            'password_confirmation' => 'correct-horse-12',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'Este e-mail já está cadastrado. Tente entrar ou recuperar sua senha.');
        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->assertSame(1, User::query()->where('email', $email)->count());
        } else {
            $this->assertSame(0, User::query()->where('email', $email)->count());
        }
    }

    public function test_register_returns_422_when_cpf_is_inserted_concurrently(): void
    {
        $cpf = User::factory()->withCpf()->make()->cpf;
        $inserted = false;
        User::creating(static function (User $user) use ($cpf, &$inserted): void {
            if ($inserted) {
                return;
            }

            $inserted = true;
            self::insertRaceRow(['email' => 'concurrent-'.Str::uuid()->toString().'@example.test', 'cpf' => $cpf]);
        });

        $response = $this->sessionHeaders()->postJson('/api/v1/register', [
            'name' => 'Race',
            'email' => 'race-'.Str::uuid()->toString().'@example.test',
            'password' => 'correct-horse-12',
            'password_confirmation' => 'correct-horse-12',
            'cpf' => $cpf,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('cpf')
            ->assertJsonPath('errors.cpf.0', 'Este CPF já está cadastrado.');
        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->assertSame(1, User::query()->where('cpf', $cpf)->count());
        } else {
            $this->assertSame(0, User::query()->where('cpf', $cpf)->count());
        }
    }

    public function test_register_keeps_account_deactivated_when_verification_dispatch_fails(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('queue unavailable'));

        $response = $this->sessionHeaders()->postJson('/api/v1/register', [
            'name' => 'Retry User',
            'email' => 'retry@example.test',
            'password' => 'correct-horse-12',
            'password_confirmation' => 'correct-horse-12',
        ]);

        $response->assertStatus(503)
            ->assertJsonPath('message', 'Sua solicitação foi recebida, mas não foi possível enviar o e-mail de verificação. Tente novamente mais tarde.');
        $user = User::query()->where('email', 'retry@example.test')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->deactivated_at);
        $this->assertFalse($user->hasAnyRole());
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
        self::actingAsSanctum($unverified);
        $this->postJson('/api/v1/email/verification-notification')
            ->assertStatus(202)
            ->assertExactJson(['message' => 'Se o email ainda não foi verificado, reenviamos o link.']);
        Notification::assertSentTo($unverified, VerifyEmail::class);

        $verified = User::factory()->create();
        self::actingAsSanctum($verified);
        $this->postJson('/api/v1/email/verification-notification')
            ->assertStatus(202)
            ->assertExactJson(['message' => 'Se o email ainda não foi verificado, reenviamos o link.']);
        Notification::assertNotSentTo($verified, VerifyEmail::class);
    }

    public function test_verify_requires_the_matching_session_and_marks_only_email_verified(): void
    {
        Event::fake([Verified::class]);
        config(['app.frontend_url' => 'http://frontend.test']);

        $user = User::factory()->unverified()->create([
            'email' => 'verify-me@example.test',
            'deactivated_at' => now(),
        ]);
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHours(24),
            ['id' => $user->id, 'hash' => hash('sha256', $user->email)]
        );

        $this->get($url)->assertUnauthorized();
        $this->assertNull($user->fresh()?->email_verified_at);

        $other = User::factory()->unverified()->create();
        self::actingAsSanctum($other);
        $this->get($url)->assertForbidden();
        $this->assertNull($user->fresh()?->email_verified_at);

        self::actingAsSanctum($user);
        $response = $this->get($url);

        $response->assertStatus(302);
        $response->assertRedirect('http://frontend.test/verify-email?ok=1');
        $this->assertNotNull($user->fresh()?->email_verified_at);
        $this->assertNotNull($user->fresh()->deactivated_at);
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $user->id]);
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

        self::actingAsSanctum($user);
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

        self::actingAsSanctum($user);
        $this->get($url)->assertForbidden();
        $this->assertNull($user->fresh()?->email_verified_at);
    }

    public function test_verify_notification_link_verifies_the_user_end_to_end(): void
    {
        config(['app.frontend_url' => 'http://frontend.test']);

        $user = User::factory()->unverified()->create(['email' => 'link-check@example.test']);
        $notification = new VerifyEmail;
        $mail = $notification->toMail($user);

        $this->get($mail->actionUrl)->assertUnauthorized();
        self::actingAsSanctum($user);
        $this->get($mail->actionUrl)->assertRedirect('http://frontend.test/verify-email?ok=1');
        $this->assertNotNull($user->fresh()?->email_verified_at);
    }

    /** @param array{email: string, name?: string, cpf?: string|null} $attributes */
    private static function insertRaceRow(array $attributes): void
    {
        $now = now();
        $data = [
            'name' => $attributes['name'] ?? 'Concurrent',
            'email' => $attributes['email'],
            'password' => 'hashed-password',
            'cpf' => $attributes['cpf'] ?? null,
            'email_verified_at' => null,
            'deactivated_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (DB::connection()->getDriverName() === 'pgsql') {
            $host = config('database.connections.pgsql.host');
            $port = config('database.connections.pgsql.port');
            $database = config('database.connections.pgsql.database');
            $username = config('database.connections.pgsql.username');
            $password = config('database.connections.pgsql.password');
            assert(is_string($host) && $host !== '');
            assert(is_string($database) && $database !== '');
            assert(is_string($username) && $username !== '');
            assert(is_string($password) || $password === null);
            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, is_scalar($port) ? (string) $port : '', $database);
            $pdo = new PDO($dsn, $username, $password ?? null);
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password, cpf, email_verified_at, deactivated_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute(array_values($data));

            return;
        }

        DB::table('users')->insert($data);
    }
}
