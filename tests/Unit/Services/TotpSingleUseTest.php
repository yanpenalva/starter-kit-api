<?php

declare(strict_types=1);

use App\Services\TotpService;

it('accepts a fresh totp code once and rejects its reuse within the window', function (): void {
    $totp = app(TotpService::class);
    $secret = $totp->generateSecret();
    $code = $totp->generateCode($secret);

    expect($totp->verifyOnce($secret, $code, 'mfa:1'))->toBeTrue();
    expect($totp->verifyOnce($secret, $code, 'mfa:1'))->toBeFalse();
});

it('tracks single-use per scope independently', function (): void {
    $totp = app(TotpService::class);
    $secret = $totp->generateSecret();
    $code = $totp->generateCode($secret);

    expect($totp->verifyOnce($secret, $code, 'mfa:1'))->toBeTrue();
    expect($totp->verifyOnce($secret, $code, 'mfa:2'))->toBeTrue();
});

it('rejects malformed codes without consuming anything', function (): void {
    $totp = app(TotpService::class);
    $secret = $totp->generateSecret();

    expect($totp->verifyOnce($secret, '12345', 'mfa:1'))->toBeFalse();
    expect($totp->verifyOnce($secret, 'abcdef', 'mfa:1'))->toBeFalse();

    $code = $totp->generateCode($secret);

    expect($totp->verifyOnce($secret, $code, 'mfa:1'))->toBeTrue();
});
