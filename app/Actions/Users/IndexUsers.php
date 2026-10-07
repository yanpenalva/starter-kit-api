<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use App\Support\Filters\UserFilters;
use Illuminate\Pagination\LengthAwarePaginator;

final class IndexUsers
{
    /**
     * @param  array<string, string>  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function run(User $actor, array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, User> */
        return UserFilters::apply(User::query()->with('roles'), $filters)
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
