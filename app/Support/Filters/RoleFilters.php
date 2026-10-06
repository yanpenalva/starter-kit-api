<?php

declare(strict_types=1);

namespace App\Support\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * Wave B contract: allowlisted query filters for role lists.
 */
final class RoleFilters
{
    /**
     * @param  Builder<\Spatie\Permission\Models\Role>  $query
     * @param  array<string, string>  $filters
     * @return Builder<\Spatie\Permission\Models\Role>
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        $search = $filters['search'] ?? null;

        if (is_string($search) && $search !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

            $query->where('name', 'ilike', '%'.$escaped.'%');
        }

        return $query;
    }
}
