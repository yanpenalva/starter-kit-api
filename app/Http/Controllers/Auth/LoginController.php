<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\ApiMessage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

final class LoginController
{
    private const DUMMY_HASH = '$2y$12$eA3Vz9kYgG76d9.7XQ5w5.Hn8HjZ4aA.xV31zX9N.zF3n0K8iUaW2';

    public function __invoke(LoginRequest $request): Response
    {
        $normalizedEmail = $request->normalizedEmail();
        $password = $request->input('password');

        if (! is_string($password)) {
            abort(401, ApiMessage::INVALID_CREDENTIALS);
        }

        /** @var User|null $user */
        $user = User::query()
            ->whereRaw('lower(btrim(email, E\' \\t\\n\\r\\x0B\')) = ?', [$normalizedEmail])
            ->first();

        if ($user === null) {
            // Mitigate timing side-channels by performing a dummy hash computation
            Hash::check($password, self::DUMMY_HASH);

            abort(401, ApiMessage::INVALID_CREDENTIALS);
        }

        if (! Hash::check($password, $user->password)) {
            abort(401, ApiMessage::INVALID_CREDENTIALS);
        }

        if ($user->deactivated_at !== null) {
            abort(403, ApiMessage::DEACTIVATED);
        }

        Auth::guard('web')->login($user);

        $request->session()->regenerate();

        return response()->noContent();
    }
}
