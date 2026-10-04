<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Every privileged mutation runs inside one PostgreSQL transaction guarded by
 * a global advisory lock, so concurrent admin changes serialize instead of
 * racing past the last-admin and self-elevation checks.
 */
final class PrivilegeTransaction
{
    private const ADVISORY_LOCK_KEY = 727423;

    /**
     * @template T
     *
     * @param  Closure(): T  $mutation
     * @return T
     */
    public static function run(Closure $mutation): mixed
    {
        return DB::transaction(function () use ($mutation): mixed {
            $locked = DB::selectOne('select pg_advisory_xact_lock(?) as acquired', [
                self::ADVISORY_LOCK_KEY,
            ]);

            if (! $locked instanceof \stdClass) {
                throw new RuntimeException('The privilege advisory lock could not be acquired.');
            }

            return $mutation();
        });
    }
}
