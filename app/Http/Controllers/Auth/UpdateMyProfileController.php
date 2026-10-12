<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\UpdateMyProfileRequest;
use App\Http\Resources\Auth\MyProfileResource;
use App\Models\User;
use App\Support\EmailLookup;
use Illuminate\Support\Facades\Hash;

final class UpdateMyProfileController
{
    public function __invoke(UpdateMyProfileRequest $request): MyProfileResource
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validated();

        if (isset($validated['name']) && is_string($validated['name'])) {
            $user->name = $validated['name'];
        }

        if (isset($validated['email']) && is_string($validated['email'])) {
            $normalizedEmail = EmailLookup::normalize($validated['email']);
            if (EmailLookup::normalize($user->email) !== $normalizedEmail) {
                $user->email = $normalizedEmail;
                $user->email_verified_at = null;
                $user->sendEmailVerificationNotification();
            }
        }

        if (isset($validated['password']) && is_string($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return new MyProfileResource($user);
    }
}
