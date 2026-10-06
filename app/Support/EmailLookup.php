<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type NormalizedBuilder Builder<User>
 */
final class EmailLookup
{
    public static function normalize(mixed $email): string
    {
        return mb_strtolower(trim(is_string($email) ? $email : ''));
    }

    /**
     * @param  NormalizedBuilder  $query
     * @return NormalizedBuilder
     */
    public static function whereNormalized(Builder $query, string $normalized): Builder
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return $query->whereRaw('lower(btrim(email, E\' \\t\\n\\r\\x0B\')) = ?', [$normalized]);
        }

        return $query->whereRaw('lower(trim(email)) = ?', [$normalized]);
    }

    public static function exists(string $normalized): bool
    {
        return self::whereNormalized(User::query(), $normalized)->exists();
    }

    public static function find(string $normalized): ?User
    {
        /** @var User|null $user */
        $user = self::whereNormalized(User::query(), $normalized)->first();

        return $user;
    }
}
