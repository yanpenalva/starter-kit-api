<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\ApiMessage;
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
        if (! app(UserPolicy::class)->view($actor, $actor)) {
            abort(403, ApiMessage::UNAUTHORIZED);
        }

        /** @var LengthAwarePaginator<int, User> */
        return UserFilters::apply(User::query()->with('roles'), $filters)
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
