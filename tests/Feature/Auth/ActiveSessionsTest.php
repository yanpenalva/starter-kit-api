<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Users\SetUserStatus;
use App\Enums\Permission;
use App\Models\User;
use App\Support\ApiMessage;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Concern\ActsAsSanctumUser;
use Tests\TestCase;

final class ActiveSessionsTest extends TestCase
{
    use ActsAsSanctumUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('session.driver', 'database');

        $this->seed(RbacCatalogueSeeder::class);
    }

    private function createSessionRow(int $userId, string $id, int $lastActivity, string $ip = '127.0.0.1', string $userAgent = 'Mozilla/5.0'): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'payload' => base64_encode(serialize(['_token' => Str::random(40)])),
            'last_activity' => $lastActivity,
        ]);
    }

    public function test_guest_cannot_access_session_endpoints(): void
    {
        $this->getJson('/api/v1/auth/sessions')->assertStatus(401);
        $this->deleteJson('/api/v1/auth/sessions/any-id')->assertStatus(401);
        $this->deleteJson('/api/v1/auth/sessions')->assertStatus(401);
    }

    public function test_session_user_id_matches_the_users_primary_key_type(): void
    {
        $this->assertSame(
            Schema::getColumnType('users', 'id'),
            Schema::getColumnType('sessions', 'user_id'),
        );
    }

    public function test_user_can_list_active_sessions_and_distinguish_current_session(): void
    {
        /** @var User $user */
        $user = User::factory()->verified()->create();

        self::actingAsSanctum($user);

        // Start session so current session ID exists in test request
        $this->withSession(['test' => 'data']);
        $currentSessionId = $this->app['session']->getId();
        $otherSessionId = Str::random(40);

        $this->createSessionRow($user->id, $currentSessionId, Carbon::now()->getTimestamp(), '192.168.1.1', 'CurrentAgent/1.0');
        $this->createSessionRow($user->id, $otherSessionId, Carbon::now()->subHour()->getTimestamp(), '192.168.1.2', 'OtherAgent/2.0');

        // Another user's session shouldn't appear
        /** @var User $anotherUser */
        $anotherUser = User::factory()->verified()->create();
        $this->createSessionRow($anotherUser->id, Str::random(40), Carbon::now()->getTimestamp());

        $response = $this->getJson('/api/v1/auth/sessions');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'ip_address',
                        'user_agent',
                        'last_activity',
                        'is_current',
                    ],
                ],
            ]);

        $items = $response->json('data');
        $this->assertIsArray($items);
        $currentFound = false;
        $otherFound = false;

        foreach ($items as $item) {
            $this->assertIsArray($item);
            if ($item['id'] === $currentSessionId) {
                $currentFound = true;
                $this->assertTrue($item['is_current']);
                $this->assertSame('192.168.1.1', $item['ip_address']);
            }
            if ($item['id'] === $otherSessionId) {
                $otherFound = true;
                $this->assertFalse($item['is_current']);
                $this->assertSame('192.168.1.2', $item['ip_address']);
            }
        }

        $this->assertTrue($currentFound);
        $this->assertTrue($otherFound);
    }

    public function test_user_can_revoke_a_specific_session(): void
    {
        /** @var User $user */
        $user = User::factory()->verified()->create();

        $sessionId1 = Str::random(40);
        $sessionId2 = Str::random(40);

        $this->createSessionRow($user->id, $sessionId1, Carbon::now()->getTimestamp());
        $this->createSessionRow($user->id, $sessionId2, Carbon::now()->getTimestamp());

        self::actingAsSanctum($user);

        $this->deleteJson("/api/v1/auth/sessions/{$sessionId1}")
            ->assertNoContent();

        $this->assertDatabaseMissing('sessions', ['id' => $sessionId1]);
        $this->assertDatabaseHas('sessions', ['id' => $sessionId2]);
    }

    public function test_user_cannot_revoke_another_users_session(): void
    {
        /** @var User $user */
        $user = User::factory()->verified()->create();
        /** @var User $victim */
        $victim = User::factory()->verified()->create();

        $victimSessionId = Str::random(40);
        $this->createSessionRow($victim->id, $victimSessionId, Carbon::now()->getTimestamp());

        self::actingAsSanctum($user);

        $this->deleteJson("/api/v1/auth/sessions/{$victimSessionId}")
            ->assertNotFound()
            ->assertJsonPath('message', ApiMessage::SESSION_NOT_FOUND);

        $this->assertDatabaseHas('sessions', ['id' => $victimSessionId]);
    }

    public function test_user_can_revoke_all_other_sessions(): void
    {
        /** @var User $user */
        $user = User::factory()->verified()->create();

        self::actingAsSanctum($user);

        $this->withSession(['test' => 'data']);
        $currentSessionId = $this->app['session']->getId();
        $other1 = Str::random(40);
        $other2 = Str::random(40);

        $this->createSessionRow($user->id, $currentSessionId, Carbon::now()->getTimestamp());
        $this->createSessionRow($user->id, $other1, Carbon::now()->getTimestamp());
        $this->createSessionRow($user->id, $other2, Carbon::now()->getTimestamp());

        $this->deleteJson('/api/v1/auth/sessions')
            ->assertNoContent();

        $this->assertDatabaseHas('sessions', ['id' => $currentSessionId]);
        $this->assertDatabaseMissing('sessions', ['id' => $other1]);
        $this->assertDatabaseMissing('sessions', ['id' => $other2]);
    }

    public function test_reset_password_invalidates_all_active_sessions_for_user(): void
    {
        $user = User::factory()->verified()->create(['email' => 'reset-sessions@example.test']);

        $session1 = Str::random(40);
        $session2 = Str::random(40);
        $this->createSessionRow($user->id, $session1, Carbon::now()->getTimestamp());
        $this->createSessionRow($user->id, $session2, Carbon::now()->getTimestamp());

        $broker = $this->app->make('auth.password.broker');
        $this->assertInstanceOf(PasswordBroker::class, $broker);
        $token = $broker->createToken($user);

        $this->postJson('/api/v1/reset-password', [
            'email' => 'reset-sessions@example.test',
            'token' => $token,
            'password' => 'new-secret-password-123',
            'password_confirmation' => 'new-secret-password-123',
        ])->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => $session1]);
        $this->assertDatabaseMissing('sessions', ['id' => $session2]);
    }

    public function test_user_suspension_invalidates_all_active_sessions_for_user(): void
    {
        $target = User::factory()->verified()->create();
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersSetStatus->value);

        $sessionTarget1 = Str::random(40);
        $sessionTarget2 = Str::random(40);
        $this->createSessionRow($target->id, $sessionTarget1, Carbon::now()->getTimestamp());
        $this->createSessionRow($target->id, $sessionTarget2, Carbon::now()->getTimestamp());

        app(SetUserStatus::class)->run($actor, $target, 'deactivated');

        $this->assertDatabaseMissing('sessions', ['id' => $sessionTarget1]);
        $this->assertDatabaseMissing('sessions', ['id' => $sessionTarget2]);
    }

    /**
     * Logs in over HTTP and returns its persisted session id.
     *
     * @return array{id: string}
     */
    private function loginOverHttp(User $user, string $password): array
    {
        $this->resetRequestState();

        $this->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/login', ['email' => $user->email, 'password' => $password])
            ->assertNoContent();

        $id = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->whereNotIn('id', array_keys($this->knownSessions))
            ->value('id');
        $this->assertIsString($id);
        $this->knownSessions[$id] = true;

        return ['id' => $id];
    }

    /** @var array<string, true> */
    private array $knownSessions = [];

    /**
     * @return array<string, string>
     */
    private function statefulHeaders(): array
    {
        return ['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'];
    }

    private function resetRequestState(): void
    {
        Auth::forgetGuards();
        $session = $this->app->make('session.store');
        $this->assertInstanceOf(Store::class, $session);
        $session->flush();
        $this->flushHeaders();
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
    }

    /**
     * Sends the real database session id through Laravel's encrypted-cookie test helper.
     */
    private function asBrowser(string $sessionId): static
    {
        $this->resetRequestState();

        $cookieName = config('session.cookie');
        $this->assertIsString($cookieName);

        return $this->withCredentials()
            ->withHeaders($this->statefulHeaders())
            ->withCookie($cookieName, $sessionId);
    }

    public function test_revoked_session_can_no_longer_authenticate(): void
    {
        $user = User::factory()->verified()->create(['password' => 'secret-password-123']);

        $keep = $this->loginOverHttp($user, 'secret-password-123');
        $victim = $this->loginOverHttp($user, 'secret-password-123');
        $this->assertNotSame($keep['id'], $victim['id']);
        $this->assertSame(2, DB::table('sessions')->where('user_id', $user->id)->count());

        // Non-vacuous: before revocation both cookies authenticate, and the
        // listing reports the real current session id for each of them.
        $list = $this->asBrowser($victim['id'])->getJson('/api/v1/auth/sessions')->assertOk();
        $data = $list->json('data');
        $this->assertIsArray($data);
        $current = collect($data)->firstWhere('is_current', true);
        $this->assertIsArray($current);
        $this->assertSame($victim['id'], $current['id']);
        $this->asBrowser($victim['id'])->getJson('/api/v1/auth/my-profile')->assertOk();

        $this->asBrowser($keep['id'])->deleteJson("/api/v1/auth/sessions/{$victim['id']}")->assertNoContent();
        $this->assertDatabaseMissing('sessions', ['id' => $victim['id']]);

        $this->asBrowser($victim['id'])->getJson('/api/v1/auth/my-profile')->assertStatus(401);
        $this->asBrowser($victim['id'])->getJson('/api/v1/auth/sessions')->assertStatus(401);
        $this->asBrowser($keep['id'])->getJson('/api/v1/auth/my-profile')->assertOk();
    }

    public function test_reset_password_leaves_other_users_sessions_untouched(): void
    {
        $user = User::factory()->verified()->create(['email' => 'reset-owner@example.test']);
        $bystander = User::factory()->verified()->create();

        $ownSession = Str::random(40);
        $bystanderSession = Str::random(40);
        $this->createSessionRow($user->id, $ownSession, Carbon::now()->getTimestamp());
        $this->createSessionRow($bystander->id, $bystanderSession, Carbon::now()->getTimestamp());

        $broker = $this->app->make('auth.password.broker');
        $this->assertInstanceOf(PasswordBroker::class, $broker);
        $token = $broker->createToken($user);

        $this->postJson('/api/v1/reset-password', [
            'email' => 'reset-owner@example.test',
            'token' => $token,
            'password' => 'new-secret-password-123',
            'password_confirmation' => 'new-secret-password-123',
        ])->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => $ownSession]);
        $this->assertDatabaseHas('sessions', ['id' => $bystanderSession]);
    }

    public function test_failed_reset_password_keeps_sessions(): void
    {
        $user = User::factory()->verified()->create(['email' => 'reset-fail@example.test']);

        $sessionId = Str::random(40);
        $this->createSessionRow($user->id, $sessionId, Carbon::now()->getTimestamp());

        $this->postJson('/api/v1/reset-password', [
            'email' => 'reset-fail@example.test',
            'token' => 'invalid-token',
            'password' => 'new-secret-password-123',
            'password_confirmation' => 'new-secret-password-123',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('sessions', ['id' => $sessionId]);
    }

    public function test_suspension_over_http_invalidates_active_sessions_and_blocks_them(): void
    {
        $target = User::factory()->verified()->create(['password' => 'secret-password-123']);
        $bystander = User::factory()->verified()->create();
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersSetStatus->value);

        $targetSession = $this->loginOverHttp($target, 'secret-password-123');
        $bystanderSession = Str::random(40);
        $this->createSessionRow($bystander->id, $bystanderSession, Carbon::now()->getTimestamp());

        // Non-vacuous: the browser cookie authenticates before suspension.
        $this->asBrowser($targetSession['id'])->getJson('/api/v1/auth/my-profile')->assertOk();

        $this->resetRequestState();
        self::actingAsSanctum($actor);
        $this->postJson("/api/v1/users/{$target->id}/status", ['status' => 'deactivated'])->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => $targetSession['id']]);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
        $this->assertDatabaseHas('sessions', ['id' => $bystanderSession]);

        $this->asBrowser($targetSession['id'])->getJson('/api/v1/auth/my-profile')->assertStatus(401);
    }

    public function test_user_cannot_revoke_others_through_another_users_sessions_bulk_endpoint(): void
    {
        $user = User::factory()->verified()->create();
        $victim = User::factory()->verified()->create();

        $victimSession1 = Str::random(40);
        $victimSession2 = Str::random(40);
        $this->createSessionRow($victim->id, $victimSession1, Carbon::now()->getTimestamp());
        $this->createSessionRow($victim->id, $victimSession2, Carbon::now()->getTimestamp());

        self::actingAsSanctum($user);
        $this->withSession(['test' => 'data']);
        $this->createSessionRow($user->id, $this->app['session']->getId(), Carbon::now()->getTimestamp());
        $userOtherSession = Str::random(40);
        $this->createSessionRow($user->id, $userOtherSession, Carbon::now()->getTimestamp());

        $this->deleteJson('/api/v1/auth/sessions')->assertNoContent();

        $this->assertDatabaseMissing('sessions', ['id' => $userOtherSession]);
        $this->assertDatabaseHas('sessions', ['id' => $victimSession1]);
        $this->assertDatabaseHas('sessions', ['id' => $victimSession2]);
    }

    public function test_session_revocation_routes_share_a_rate_limit(): void
    {
        $user = User::factory()->verified()->create();

        self::actingAsSanctum($user);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->deleteJson('/api/v1/auth/sessions/missing-session')->assertNotFound();
        }

        $this->deleteJson('/api/v1/auth/sessions')->assertStatus(429);
    }
}
