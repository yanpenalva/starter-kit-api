<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Support\EmailLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

final class ForgotPasswordController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{email: string} $validated */
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $user = EmailLookup::find(EmailLookup::normalize($validated['email']));

        if ($user !== null && $user->deactivated_at === null) {
            Password::broker()->sendResetLink(['email' => $user->email]);
        }

        return response()->json(['message' => 'Se o email existir, enviamos o link.'], 202);
    }
}
