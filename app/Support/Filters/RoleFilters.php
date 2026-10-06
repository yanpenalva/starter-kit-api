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
        $search = $filters['search'] ?? null;

        if (is_string($search) && $search !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

            if (DB::connection()->getDriverName() === 'pgsql') {
                $query->whereRaw("name ilike ? ESCAPE '\\'", ['%'.$escaped.'%']);

                return $query;
            }

            $query->whereRaw("name like ? ESCAPE '\\'", ['%'.$escaped.'%']);
        }

        return $query;
    }
}
