<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class MfaRecoveryCodeService
{
    /**
     * @param  array<int, string>  $codes
     * @return array<int, string>
     */
    public function hash(array $codes): array
    {
        return array_map(Hash::make(...), $codes);
    }

    public function consume(int $userId, string $code): bool
    {
        return DB::transaction(function () use ($userId, $code): bool {
            /** @var User|null $user */
            $user = User::query()->lockForUpdate()->find($userId);

            if ($user === null || ! $user->hasMfaEnabled() || ! is_array($user->mfa_recovery_codes)) {
                return false;
            }

            $codes = $user->mfa_recovery_codes;

            foreach ($codes as $index => $hash) {
                if (! Hash::check($code, $hash)) {
                    continue;
                }

                unset($codes[$index]);
                $user->forceFill([
                    'mfa_recovery_codes' => array_values($codes),
                ])->save();

                return true;
            }

            return false;
        });
    }
}
