<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use App\Services\MfaRecoveryCodeService;
use App\Services\TotpService;
use App\Support\ApiMessage;
use App\Support\BlockedAccount;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concern\ActsAsSanctumUser;
use Tests\TestCase;

final class MfaControllerCoverageTest extends TestCase
{
    use ActsAsSanctumUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacCatalogueSeeder::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeAdmin(array $attributes = []): User
    {
        $admin = User::factory()->create($attributes);
        $admin->assignRole(Role::Admin->value);

        return $admin;
    }

    private function enableMfa(User $user): string
    {
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        $user->forceFill([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => $this->recoveryCodeService()->hash($totp->generateRecoveryCodes()),
            'mfa_confirmed_at' => now(),
        ])->save();

        return $secret;
    }

    private function recoveryCodeService(): MfaRecoveryCodeService
    {
        return app(MfaRecoveryCodeService::class);
    }

    /**
     * The api middleware group only starts a session for stateful requests
     * (Origin/Referer on a Sanctum stateful domain), and EnsureMfaChallenge
     * reads the challenge state from that session.
     *
     * @return array<string, string>
     */
    private function statefulHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ];
    }

    private function codeOutsideWindow(string $secret): string
    {
        $totp = app(TotpService::class);
        $accepted = [];

        for ($offset = -6; $offset <= 6; $offset++) {
            $accepted[] = $totp->generateCode($secret, time() + $offset * 30);
        }

        foreach (range(0, 999) as $candidate) {
            $formatted = str_pad((string) $candidate, 6, '0', STR_PAD_LEFT);

            if (! in_array($formatted, $accepted, true)) {
                return $formatted;
            }
        }

        self::fail('No TOTP code outside the acceptance window could be derived.');
    }

    public function test_confirm_without_enrollment_returns_bad_request(): void
    {
        $admin = $this->makeAdmin();
        self::actingAsSanctum($admin);

        $response = $this->postJson('/api/v1/auth/mfa/confirm', ['code' => '123456']);

        $response->assertStatus(400)
            ->assertJson(['message' => ApiMessage::REQUEST_FAILED]);

        $admin->refresh();
        $this->assertNull($admin->mfa_secret);
        $this->assertNull($admin->mfa_confirmed_at);
        $this->assertFalse($admin->hasMfaEnabled());
    }

    public function test_regenerate_without_active_mfa_returns_bad_request(): void
    {
        $admin = $this->makeAdmin();
        self::actingAsSanctum($admin);

        $response = $this->postJson('/api/v1/auth/mfa/recovery-codes');

        $response->assertStatus(400)
            ->assertJson(['message' => ApiMessage::REQUEST_FAILED]);

        $admin->refresh();
        $this->assertNull($admin->mfa_recovery_codes);
        $this->assertFalse($admin->hasMfaEnabled());
    }

    public function test_regenerate_with_active_mfa_returns_new_hashed_codes(): void
    {
        $admin = $this->makeAdmin();
        $secret = $this->enableMfa($admin);
        self::actingAsSanctum($admin);

        $response = $this->withSession(['mfa:verified_user_id' => $admin->id])
            ->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/mfa/recovery-codes');

        $response->assertOk()->assertJsonStructure(['recovery_codes']);
        $codes = $response->json('recovery_codes');
        $this->assertIsArray($codes);
        $this->assertCount(8, $codes);

        $admin->refresh();
        $this->assertTrue($admin->hasMfaEnabled());
        $this->assertSame($secret, $admin->mfa_secret);

        $stored = $admin->mfa_recovery_codes;
        $this->assertIsArray($stored);
        $this->assertCount(8, $stored);

        $dump = json_encode($stored);
        $this->assertIsString($dump);

        foreach ($codes as $index => $plain) {
            $this->assertIsString($plain);
            $hash = $stored[$index] ?? null;
            $this->assertIsString($hash);
            $this->assertStringNotContainsString($plain, $dump);
            $this->assertTrue(Hash::check($plain, $hash));
        }
    }

    public function test_recovery_code_consume_returns_false_without_active_mfa(): void
    {
        $service = $this->recoveryCodeService();
        $admin = $this->makeAdmin();

        $this->assertFalse($service->consume((int) $admin->id, 'ABCD-1234'));

        $admin->forceFill([
            'mfa_secret' => app(TotpService::class)->generateSecret(),
            'mfa_confirmed_at' => now(),
        ])->save();
        $admin->refresh();
        $this->assertTrue($admin->hasMfaEnabled());
        $this->assertNull($admin->mfa_recovery_codes);

        $this->assertFalse($service->consume((int) $admin->id, 'ABCD-1234'));
        $this->assertFalse($service->consume(999999, 'ABCD-1234'));
    }

    public function test_totp_verify_rejects_malformed_codes(): void
    {
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();

        $this->assertFalse($totp->verify($secret, ''));
        $this->assertFalse($totp->verify($secret, '12345'));
        $this->assertFalse($totp->verify($secret, '1234567'));
        $this->assertFalse($totp->verify($secret, 'abcdef'));
        $this->assertFalse($totp->verify($secret, '12 456'));
    }

    public function test_totp_verify_accepts_codes_inside_the_window_and_rejects_the_others(): void
    {
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        $now = time();

        $this->assertTrue($totp->verify($secret, $totp->generateCode($secret, $now)));
        $this->assertTrue($totp->verify($secret, $totp->generateCode($secret, $now - 30)));
        $this->assertFalse($totp->verify($secret, $totp->generateCode($secret, $now - 600)));
        $this->assertFalse($totp->verify($secret, $this->codeOutsideWindow($secret)));
    }

    public function test_totp_verify_ignores_characters_outside_the_base32_alphabet(): void
    {
        $totp = app(TotpService::class);
        $timestamp = 1_700_000_000;

        $this->assertSame(
            $totp->generateCode('SECRET', $timestamp),
            $totp->generateCode('SECRET1', $timestamp),
        );

        $code = $totp->generateCode('SECRET1');

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertTrue($totp->verify('SECRET1', $code));
    }

    public function test_challenge_without_any_code_returns_unprocessable(): void
    {
        $admin = $this->makeAdmin();
        $this->enableMfa($admin);

        $response = $this->withSession(['mfa:user_id' => $admin->id])
            ->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/mfa/challenge', []);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Informe o código MFA ou um código de recuperação.',
            ])
            ->assertJsonValidationErrors('code');
        $this->assertGuest('web');
    }

    public function test_challenge_with_unknown_user_returns_unauthorized(): void
    {
        $response = $this->withSession(['mfa:user_id' => 999999])
            ->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/mfa/challenge', ['code' => '123456']);

        $response->assertUnauthorized();
        $this->assertGuest('web');
    }

    public function test_challenge_with_user_without_mfa_returns_unauthorized(): void
    {
        $plain = $this->makeAdmin();
        self::actingAsSanctum($plain);

        $response = $this->withSession(['mfa:user_id' => $plain->id])
            ->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/mfa/challenge', ['code' => '123456']);

        $response->assertUnauthorized();
        $this->assertGuest('web');
    }

    public function test_challenge_with_invalid_code_returns_unprocessable(): void
    {
        $admin = $this->makeAdmin();
        $secret = $this->enableMfa($admin);
        $invalidCode = $this->codeOutsideWindow($secret);

        $response = $this->withSession(['mfa:user_id' => $admin->id])
            ->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/mfa/challenge', ['code' => $invalidCode]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Código MFA ou de recuperação inválido.',
            ])
            ->assertJsonValidationErrors('code');
        $this->assertGuest('web');
        $this->assertSame($admin->id, session('mfa:user_id'));
    }

    public function test_challenge_with_deactivated_verified_user_returns_blocked_response(): void
    {
        $admin = $this->makeAdmin(['email_verified_at' => now()]);
        $this->enableMfa($admin);
        $admin->forceFill(['deactivated_at' => now()])->save();
        $admin->refresh();
        $this->assertNotNull($admin->deactivated_at);
        $this->assertTrue($admin->hasVerifiedEmail());

        $expected = BlockedAccount::payload($admin);

        $response = $this->withSession(['mfa:user_id' => $admin->id])
            ->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/auth/mfa/challenge', ['code' => '123456']);

        $response->assertForbidden()->assertJson($expected);
        $this->assertGuest('web');
        $this->assertNull(session('mfa:user_id'));
    }
}
