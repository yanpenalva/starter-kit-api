<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Every privileged mutation runs inside one database transaction. On
 * PostgreSQL a global advisory lock serializes concurrent admin changes so
 * they cannot race past the last-admin and self-elevation checks; other
 * drivers (local sqlite runs) rely on the transaction alone. The permission
 * cache is cleared under the lock before policy checks use current authority.
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
            if (DB::connection()->getDriverName() === 'pgsql') {
                $locked = DB::selectOne('select pg_advisory_xact_lock(?) as acquired', [
                    self::ADVISORY_LOCK_KEY,
                ]);

                if (! $locked instanceof \stdClass) {
                    throw new RuntimeException('The privilege advisory lock could not be acquired.');
                }
            }

            return $mutation();
        });
    }

    /**
     * @template T
     *
     * @param  Closure(User): T  $mutation
     * @return T
     */
    public static function runWithActor(User $actor, Closure $mutation): mixed
    {
        return self::run(function () use ($actor, $mutation): mixed {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $currentActor = User::query()
                ->lockForUpdate()
                ->whereKey($actor->getKey())
                ->firstOrFail();

            return $mutation($currentActor);
        });
    }
}
