<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\TotpService;
use Tests\TestCase;

final class TotpSingleUseTest extends TestCase
{
    public function test_accepts_a_fresh_totp_code_once_and_rejects_its_reuse_within_the_window(): void
    {
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        $code = $totp->generateCode($secret);

        $this->assertTrue($totp->verifyOnce($secret, $code, 'mfa:1'));
        $this->assertFalse($totp->verifyOnce($secret, $code, 'mfa:1'));
    }

    public function test_tracks_single_use_per_scope_independently(): void
    {
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        $code = $totp->generateCode($secret);

        $this->assertTrue($totp->verifyOnce($secret, $code, 'mfa:1'));
        $this->assertTrue($totp->verifyOnce($secret, $code, 'mfa:2'));
    }

    public function test_rejects_malformed_codes_without_consuming_anything(): void
    {
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();

        $this->assertFalse($totp->verifyOnce($secret, '12345', 'mfa:1'));
        $this->assertFalse($totp->verifyOnce($secret, 'abcdef', 'mfa:1'));

        $code = $totp->generateCode($secret);

        $this->assertTrue($totp->verifyOnce($secret, $code, 'mfa:1'));
    }
}
