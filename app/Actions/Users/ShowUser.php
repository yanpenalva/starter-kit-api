<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\ApiMessage;

final class ShowUser
{
    public function run(User $actor, User $user): User
    {
        if (! app(UserPolicy::class)->view($actor, $user)) {
            abort(403, ApiMessage::UNAUTHORIZED);
        }

        return $user;
    }
}
