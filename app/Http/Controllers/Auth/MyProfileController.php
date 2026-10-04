<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Resources\Auth\MyProfileResource;
use App\Models\User;
use Illuminate\Http\Request;

final class MyProfileController
{
    public function __invoke(Request $request): MyProfileResource
    {
        /** @var User $user */
        $user = $request->user();

        return new MyProfileResource($user);
    }
}
