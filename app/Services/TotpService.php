<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Str;

final class TotpService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * @param  int<1, max>  $length
     */
    public function generateSecret(int $length = 16): string
    {
        $secret = '';
        $bytes = random_bytes($length);

        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_ALPHABET[ord($bytes[$i]) % 32];
        }

        return $secret;
    }

    public function getProvisioningUri(string $secret, string $accountName, string $issuer = 'StarterKit'): string
    {
        $encodedIssuer = rawurlencode($issuer);
        $encodedAccount = rawurlencode($accountName);

        return "otpauth://totp/{$encodedIssuer}:{$encodedAccount}?secret={$secret}&issuer={$encodedIssuer}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * @return array<int, string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = sprintf(
                '%s-%s',
                Str::upper(Str::random(4)),
                Str::upper(Str::random(4)),
            );
        }

        return $codes;
    }

    public function generateCode(string $secret, ?int $timestamp = null): string
    {
        $time = $timestamp ?? time();
        $timeSlice = (int) floor($time / 30);

        return $this->calculateCode($secret, $timeSlice);
    }

    public function verify(string $secret, string $code, int $window = 1): bool
    {
        if (strlen($code) !== 6 || ! ctype_digit($code)) {
            return false;
        }

        $currentTimeSlice = (int) floor(time() / 30);

        for ($i = -$window; $i <= $window; $i++) {
            $calculated = $this->calculateCode($secret, $currentTimeSlice + $i);
            if (hash_equals($calculated, $code)) {
                return true;
            }
        }

        return false;
    }

    private function calculateCode(string $secret, int $timeSlice): string
    {
        $secretBytes = $this->base32Decode($secret);
        $data = pack('N*', 0, $timeSlice);
        $hash = hash_hmac('sha1', $data, $secretBytes, true);

        $offset = ord($hash[19]) & 0x0F;
        $binaryCode = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        $otp = $binaryCode % 1000000;

        return str_pad((string) $otp, 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $b32): string
    {
        $b32 = strtoupper(str_replace('=', '', $b32));
        $binary = '';

        for ($i = 0; $i < strlen($b32); $i++) {
            $pos = strpos(self::BASE32_ALPHABET, $b32[$i]);
            if ($pos === false) {
                continue;
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        for ($i = 0; $i + 8 <= strlen($binary); $i += 8) {
            $val = (int) bindec(substr($binary, $i, 8));
            if ($val >= 0 && $val <= 255) {
                $bytes .= chr($val);
            }
        }

        return $bytes;
    }
}
