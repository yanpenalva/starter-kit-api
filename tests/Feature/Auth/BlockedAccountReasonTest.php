<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\ApiMessage;
use App\Support\BlockedAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BlockedAccountReasonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.default');
        $this->assertContains($connection, ['pgsql', 'sqlite']);
    }

    public function test_pending_approval_profile_returns_403_with_reason_and_email_verified_at(): void
    {
        $verifiedAt = now()->floorSecond();
        $user = User::factory()->create([
            'email' => 'pending-approval@example.test',
            'email_verified_at' => $verifiedAt,
            'deactivated_at' => $verifiedAt->clone()->subDay(),
        ]);

        $response = $this->actingAs($user, 'web')
            ->getJson('/api/v1/auth/my-profile');

        $response->assertStatus(403)
            ->assertJson([
                'message' => ApiMessage::DEACTIVATED,
                'reason' => BlockedAccount::REASON_PENDING_APPROVAL,
                'email_verified_at' => $verifiedAt->clone()->utc()->format('Y-m-d\TH:i:s\Z'),
            ]);
    }

    public function test_suspended_profile_returns_403_with_reason_and_email_verified_at(): void
    {
        $verifiedAt = now()->floorSecond()->subDay();
        $user = User::factory()->create([
            'email' => 'suspended@example.test',
            'email_verified_at' => $verifiedAt,
            'deactivated_at' => now()->floorSecond(),
        ]);

        $response = $this->actingAs($user, 'web')
            ->getJson('/api/v1/auth/my-profile');

        $response->assertStatus(403)
            ->assertJson([
                'message' => ApiMessage::DEACTIVATED,
                'reason' => BlockedAccount::REASON_SUSPENDED,
                'email_verified_at' => $verifiedAt->clone()->utc()->format('Y-m-d\TH:i:s\Z'),
            ]);
    }

    public function test_suspended_login_returns_403_with_reason_and_email_verified_at(): void
    {
        $verifiedAt = now()->floorSecond()->subDay();
        User::factory()->create([
            'email' => 'suspended-login@example.test',
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
                'email' => 'suspended-login@example.test',
                'password' => 'secret123',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => ApiMessage::DEACTIVATED,
                'reason' => BlockedAccount::REASON_SUSPENDED,
                'email_verified_at' => $verifiedAt->clone()->utc()->format('Y-m-d\TH:i:s\Z'),
            ]);
    }

    public function test_unverified_blocked_account_resolves_to_pending_approval(): void
    {
        $user = User::factory()->unverified()->deactivated()->make();

        $this->assertSame(BlockedAccount::REASON_PENDING_APPROVAL, BlockedAccount::reason($user));
        $this->assertNull(BlockedAccount::emailVerifiedAt($user));
    }
}
