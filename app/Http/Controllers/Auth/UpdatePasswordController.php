<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

final class UpdatePasswordController
{
    public function __invoke(UpdatePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'password' => Hash::make($request->password()),
        ])->save();

        return response()->json(['message' => 'Senha alterada com sucesso.']);
    }
}
