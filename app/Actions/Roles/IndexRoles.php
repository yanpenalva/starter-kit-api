<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Models\User;
use App\Support\Filters\RoleFilters;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Role;

final class IndexRoles
{
    /**
     * @param  array<string, string>  $filters
     * @return LengthAwarePaginator<int, Role>
     */
    public function run(User $actor, array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, Role> */
        return RoleFilters::apply(Role::query(), $filters)
            ->with('permissions')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
