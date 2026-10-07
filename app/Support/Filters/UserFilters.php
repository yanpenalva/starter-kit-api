<?php

declare(strict_types=1);

namespace App\Support\Filters;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Wave B contract: allowlisted query filters for user lists. Unknown keys
 * are ignored; the owning request validates the accepted ones.
 */
final class UserFilters
{
    /**
     * @param  Builder<User>  $query
     * @param  array<string, string>  $filters
     * @return Builder<User>
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        $search = self::present($filters['search'] ?? null);
        $status = self::present($filters['status'] ?? null);
        $role = self::present($filters['role'] ?? null);

        return $query
            ->when($search !== null, static function (Builder $filtered) use ($search): void {
                assert(is_string($search));
                self::applySearch($filtered, $search);
            })
            ->when($status === 'active', static fn (Builder $filtered): Builder => $filtered->whereNull('deactivated_at'))
            ->when($status === 'deactivated', static fn (Builder $filtered): Builder => $filtered->whereNotNull('deactivated_at'))
            ->when($role !== null, static function (Builder $filtered) use ($role): void {
                assert(is_string($role));
                $filtered->whereHas('roles', static function (Builder $byName) use ($role): void {
                    $byName->where('name', $role);
                });
            });
    }

    /**
     * @param  Builder<User>  $query
     */
    private static function applySearch(Builder $query, string $search): void
    {
        $escaped = '%'.self::escapeLike($search).'%';

        $query->where(static function (Builder $nested) use ($escaped): void {
            $nested
                ->when(self::isPostgres(), static function (Builder $postgres) use ($escaped): void {
                    $postgres->whereRaw("name ilike ? ESCAPE '\\'", [$escaped])
                        ->orWhereRaw("email ilike ? ESCAPE '\\'", [$escaped]);
                })
                ->when(! self::isPostgres(), static function (Builder $fallback) use ($escaped): void {
                    $fallback->whereRaw("name like ? ESCAPE '\\'", [$escaped])
                        ->orWhereRaw("email like ? ESCAPE '\\'", [$escaped]);
                });
        });
    }

    private static function isPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private static function present(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
