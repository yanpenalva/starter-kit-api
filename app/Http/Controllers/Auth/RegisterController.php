<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class RegisterController
{
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        /** @var array{name: string, email: string, password: string, cpf: string|null} $validated */
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'cpf' => $validated['cpf'] ?? null,
            'email_verified_at' => null,
            'deactivated_at' => null,
        ]);

        $user->sendEmailVerificationNotification();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
        ], 201);
    }
}
