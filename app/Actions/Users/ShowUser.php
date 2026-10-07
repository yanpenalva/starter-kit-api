<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;

final class ShowUser
{
    public function run(User $actor, User $user): User
    {
        return $user;
    }
}
