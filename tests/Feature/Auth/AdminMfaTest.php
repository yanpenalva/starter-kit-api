<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use App\Services\TotpService;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concern\ActsAsSanctumUser;
use Tests\TestCase;

final class AdminMfaTest extends TestCase
{
    use ActsAsSanctumUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacCatalogueSeeder::class);
    }

    public function test_non_admin_cannot_enroll_in_mfa(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::User->value);

        self::actingAsSanctum($user);

        $response = $this->postJson('/api/v1/auth/mfa/enroll');

        $response->assertForbidden();
    }

    public function test_admin_can_view_mfa_status_and_enroll(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        self::actingAsSanctum($admin);

        $statusBefore = $this->getJson('/api/v1/auth/mfa/status');
        $statusBefore->assertOk()->assertJson(['mfa_enabled' => false]);

        $enrollResponse = $this->postJson('/api/v1/auth/mfa/enroll');
        $enrollResponse->assertOk()
            ->assertJsonStructure(['secret', 'provisioning_uri', 'recovery_codes']);

        $secret = $enrollResponse->json('secret');
        $this->assertIsString($secret);
        $this->assertNotEmpty($secret);

        $recoveryCodes = $enrollResponse->json('recovery_codes');
        $this->assertIsArray($recoveryCodes);
        $this->assertCount(8, $recoveryCodes);

        // Status is still false until confirmed
        $admin->refresh();
        $this->assertFalse($admin->hasMfaEnabled());
    }

    public function test_admin_confirm_mfa_fails_with_invalid_code(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        self::actingAsSanctum($admin);

        $this->postJson('/api/v1/auth/mfa/enroll');

        $response = $this->postJson('/api/v1/auth/mfa/confirm', [
            'code' => '000000',
        ]);

        $response->assertUnprocessable();
        $admin->refresh();
        $this->assertFalse($admin->hasMfaEnabled());
    }

    public function test_admin_confirm_mfa_succeeds_with_valid_totp_code(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        self::actingAsSanctum($admin);

        $enrollResponse = $this->postJson('/api/v1/auth/mfa/enroll');
        $secret = $enrollResponse->json('secret');
        $this->assertIsString($secret);

        $totp = app(TotpService::class);
        $validCode = $totp->generateCode($secret);

        $confirmResponse = $this->postJson('/api/v1/auth/mfa/confirm', [
            'code' => $validCode,
        ]);

        $confirmResponse->assertOk()->assertJson(['confirmed' => true]);

        $admin->refresh();
        $this->assertTrue($admin->hasMfaEnabled());

        $statusAfter = $this->getJson('/api/v1/auth/mfa/status');
        $statusAfter->assertOk()->assertJson(['mfa_enabled' => true]);
    }

    public function test_admin_with_mfa_login_requires_challenge(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => 'secret123',
        ]);
        $admin->assignRole(Role::Admin->value);

        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        $recoveryCodes = $totp->generateRecoveryCodes();

        $admin->forceFill([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => $recoveryCodes,
            'mfa_confirmed_at' => now(),
        ])->save();

        $response = $this->withSession(['prev' => 'val'])
            ->withHeaders([
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ])
            ->postJson('/api/v1/login', [
                'email' => 'admin@example.test',
                'password' => 'secret123',
            ]);

        $response->assertOk()
            ->assertJson(['requires_mfa' => true]);

        $this->assertGuest('web');
        $this->assertSame($admin->id, session('mfa:user_id'));
    }

    public function test_mfa_challenge_succeeds_with_totp_code(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        $admin->forceFill([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => $totp->generateRecoveryCodes(),
            'mfa_confirmed_at' => now(),
        ])->save();

        $validCode = $totp->generateCode($secret);

        $response = $this->withSession(['mfa:user_id' => $admin->id])
            ->withHeaders([
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ])
            ->postJson('/api/v1/auth/mfa/challenge', [
                'code' => $validCode,
            ]);

        $response->assertNoContent();
        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertNull(session('mfa:user_id'));
    }

    public function test_mfa_challenge_succeeds_with_recovery_code_and_consumes_it(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        $codes = $totp->generateRecoveryCodes();
        $usedCode = $codes[0];

        $admin->forceFill([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => $codes,
            'mfa_confirmed_at' => now(),
        ])->save();

        $response = $this->withSession(['mfa:user_id' => $admin->id])
            ->withHeaders([
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ])
            ->postJson('/api/v1/auth/mfa/challenge', [
                'recovery_code' => $usedCode,
            ]);

        $response->assertNoContent();
        $this->assertAuthenticatedAs($admin, 'web');

        $admin->refresh();
        $remaining = $admin->mfa_recovery_codes ?? [];
        $this->assertCount(7, $remaining);
        $this->assertNotContains($usedCode, $remaining);

        // Trying to use same recovery code again fails
        $responseReuse = $this->withSession(['mfa:user_id' => $admin->id])
            ->withHeaders([
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ])
            ->postJson('/api/v1/auth/mfa/challenge', [
                'recovery_code' => $usedCode,
            ]);

        $responseReuse->assertUnprocessable();
    }

    public function test_mfa_challenge_fails_without_session(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ])->postJson('/api/v1/auth/mfa/challenge', [
            'code' => '123456',
        ]);

        $response->assertUnauthorized();
    }

    public function test_admin_can_disable_mfa(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $totp = app(TotpService::class);
        $admin->forceFill([
            'mfa_secret' => $totp->generateSecret(),
            'mfa_recovery_codes' => $totp->generateRecoveryCodes(),
            'mfa_confirmed_at' => now(),
        ])->save();

        self::actingAsSanctum($admin);

        $response = $this->postJson('/api/v1/auth/mfa/disable');
        $response->assertNoContent();

        $admin->refresh();
        $this->assertFalse($admin->hasMfaEnabled());
        $this->assertNull($admin->mfa_secret);
        $this->assertNull($admin->mfa_recovery_codes);
        $this->assertNull($admin->mfa_confirmed_at);
    }
}
