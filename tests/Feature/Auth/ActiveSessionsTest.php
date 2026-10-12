<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Users\SetUserStatus;
use App\Enums\Permission;
use App\Models\User;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
            ->assertStatus(404);

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

        $token = app('auth.password.broker')->createToken($user);

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
}
