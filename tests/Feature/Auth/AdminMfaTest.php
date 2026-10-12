<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use App\Services\TotpService;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        $admin = User::factory()->create(['password' => 'secret123']);
        $admin->assignRole(Role::Admin->value);

        self::actingAsSanctum($admin);

        $statusBefore = $this->getJson('/api/v1/auth/mfa/status');
        $statusBefore->assertOk()->assertJson(['mfa_enabled' => false]);

        $enrollResponse = $this->postJson('/api/v1/auth/mfa/enroll', [
            'password' => 'secret123',
        ]);
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

    public function test_admin_must_provide_the_current_password_to_enroll_in_mfa(): void
    {
        $admin = User::factory()->create(['password' => 'secret123']);
        $admin->assignRole(Role::Admin->value);

        self::actingAsSanctum($admin);

        $this->postJson('/api/v1/auth/mfa/enroll')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->postJson('/api/v1/auth/mfa/enroll', [
            'password' => 'incorrect',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $freshAdmin = $admin->fresh();
        $this->assertNotNull($freshAdmin);
        $this->assertNull($freshAdmin->mfa_secret);
    }

    public function test_recovery_code_dump_contains_only_one_way_hashes(): void
    {
        $admin = User::factory()->create(['password' => 'secret123']);
        $admin->assignRole(Role::Admin->value);

        self::actingAsSanctum($admin);

        $codes = $this->postJson('/api/v1/auth/mfa/enroll', [
            'password' => 'secret123',
        ])->assertOk()->json('recovery_codes');

        $this->assertIsArray($codes);
        $this->assertNotEmpty($codes);

        $dump = DB::table('users')->where('id', $admin->id)->value('mfa_recovery_codes');
        $this->assertIsString($dump);

        $hashes = json_decode($dump, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($hashes);
        $this->assertCount(count($codes), $hashes);

        foreach ($codes as $index => $recoveryCode) {
            $this->assertIsString($recoveryCode);
            $this->assertStringNotContainsString($recoveryCode, $dump);

            $hash = $hashes[$index] ?? null;
            $this->assertIsString($hash);
            $this->assertTrue(Hash::check($recoveryCode, $hash));
        }
    }

    public function test_admin_confirm_mfa_fails_with_invalid_code(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        self::actingAsSanctum($admin);

        $this->postJson('/api/v1/auth/mfa/enroll', ['password' => 'password']);

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

        $enrollResponse = $this->postJson('/api/v1/auth/mfa/enroll', [
            'password' => 'password',
        ]);
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

        $this->getJson('/api/v1/auth/mfa/status')->assertUnauthorized();
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

        $admin->forceFill([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => [],
            'mfa_confirmed_at' => now(),
        ])->save();

        $this->withSession(['prev' => 'val']);
        self::actingAsSanctum($admin);
        Auth::guard('web')->login($admin);
        $this->assertAuthenticatedAs($admin, 'web');

        $response = $this->withHeaders([
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
        $this->assertNull(session('prev'));
        $this->getJson('/api/v1/auth/my-profile')->assertUnauthorized();
    }

    public function test_existing_session_cannot_access_protected_routes_after_mfa_is_enabled(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $this->withSession([]);
        self::actingAsSanctum($admin);
        Auth::guard('web')->login($admin);

        $totp = app(TotpService::class);
        $admin->forceFill([
            'mfa_secret' => $totp->generateSecret(),
            'mfa_recovery_codes' => [],
            'mfa_confirmed_at' => now(),
        ])->save();

        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ]);

        foreach ([
            ['GET', '/api/v1/auth/my-profile'],
            ['GET', '/api/v1/email/verify/1/invalid'],
            ['POST', '/api/v1/email/verification-notification'],
            ['GET', '/api/v1/users'],
            ['GET', '/api/v1/roles'],
            ['GET', '/api/v1/activity-log'],
        ] as [$method, $route]) {
            $this->json($method, $route, [])->assertUnauthorized();
        }
    }

    public function test_mfa_challenge_succeeds_with_totp_code(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        $admin->forceFill([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => [],
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
        $this->assertSame($admin->id, session('mfa:verified_user_id'));
        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ])->getJson('/api/v1/auth/my-profile')->assertOk();
    }

    public function test_mfa_challenge_succeeds_with_recovery_code_and_consumes_it(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        $codes = $totp->generateRecoveryCodes();
        $storedCodes = array_slice($codes, 0, 2);
        $usedCode = $storedCodes[0];

        $admin->forceFill([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => array_map(Hash::make(...), $storedCodes),
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
        $this->assertCount(1, $remaining);
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
            'mfa_recovery_codes' => [],
            'mfa_confirmed_at' => now(),
        ])->save();

        self::actingAsSanctum($admin);

        $response = $this->withSession(['mfa:verified_user_id' => $admin->id])
            ->withHeaders([
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ])
            ->postJson('/api/v1/auth/mfa/disable');
        $response->assertNoContent();

        $admin->refresh();
        $this->assertFalse($admin->hasMfaEnabled());
        $this->assertNull($admin->mfa_secret);
        $this->assertNull($admin->mfa_recovery_codes);
        $this->assertNull($admin->mfa_confirmed_at);
    }
}
