<?php

declare(strict_types=1);

namespace App\Support\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Wave B contract: allowlisted query filters for role lists.
 */
final class RoleFilters
{
    /**
     * @param  Builder<Role>  $query
     * @param  array<string, string>  $filters
     * @return Builder<Role>
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        $search = self::present($filters['search'] ?? null);

        return $query->when($search !== null, static function (Builder $filtered) use ($search): void {
            assert(is_string($search));
            self::applySearch($filtered, $search);
        });
    }

    /**
     * @param  Builder<Role>  $query
     */
    private static function applySearch(Builder $query, string $search): void
    {
        $escaped = '%'.self::escapeLike($search).'%';

        $query
            ->when(self::isPostgres(), static function (Builder $postgres) use ($escaped): void {
                $postgres->whereRaw("name ilike ? ESCAPE '\\'", [$escaped]);
            })
            ->when(! self::isPostgres(), static function (Builder $fallback) use ($escaped): void {
                $fallback->whereRaw("name like ? ESCAPE '\\'", [$escaped]);
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
