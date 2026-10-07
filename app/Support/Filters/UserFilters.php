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
        $search = $filters['search'] ?? null;

        if (is_string($search) && $search !== '') {
            $escaped = self::escapeLike($search);

            $query->where(static function (Builder $query) use ($escaped): void {
                if (DB::connection()->getDriverName() === 'pgsql') {
                    $query->whereRaw("name ilike ? ESCAPE '\\'", ['%'.$escaped.'%'])
                        ->orWhereRaw("email ilike ? ESCAPE '\\'", ['%'.$escaped.'%']);

                    return;
                }

                $query->whereRaw("name like ? ESCAPE '\\'", ['%'.$escaped.'%'])
                    ->orWhereRaw("email like ? ESCAPE '\\'", ['%'.$escaped.'%']);
            });
        }

        $status = $filters['status'] ?? null;

        if ($status === 'active') {
            $query->whereNull('deactivated_at');
        } elseif ($status === 'deactivated') {
            $query->whereNotNull('deactivated_at');
        }

        $role = $filters['role'] ?? null;

        if (is_string($role) && $role !== '') {
            $query->whereHas('roles', static function (Builder $query) use ($role): void {
                $query->where('name', $role);
            });
        }

        return $query;
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
