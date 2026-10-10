<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\ApiMessage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class LoginController
{
    private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$Um9Ic3I1dklKc1RSclNUUg$oEBOGmmVHiKfWhxKBJr7KfEScyC2h3kqlm8fMFcgxlo';

    public function __invoke(LoginRequest $request): Response
    {
        $normalizedEmail = $request->normalizedEmail();
        $password = $request->input('password');

        if (! is_string($password)) {
            abort(SymfonyResponse::HTTP_UNAUTHORIZED, ApiMessage::INVALID_CREDENTIALS);
        }

        $query = User::query();

        if (DB::connection()->getDriverName() === 'pgsql') {
            $query->whereRaw('lower(btrim(email, E\' \\t\\n\\r\\x0B\')) = ?', [$normalizedEmail]);
        } else {
            $query->whereRaw("lower(trim(email, ' ' || char(9) || char(10) || char(13) || char(11))) = ?", [$normalizedEmail]);
        }

        /** @var User|null $user */
        $user = $query->first();

        if ($user === null) {
            Hash::check($password, self::DUMMY_HASH);

            abort(SymfonyResponse::HTTP_UNAUTHORIZED, ApiMessage::INVALID_CREDENTIALS);
        }

        if (! Hash::check($password, $user->password)) {
            abort(SymfonyResponse::HTTP_UNAUTHORIZED, ApiMessage::INVALID_CREDENTIALS);
        }

        if ($user->deactivated_at !== null) {
            abort(SymfonyResponse::HTTP_FORBIDDEN, ApiMessage::DEACTIVATED);
        }

        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => Hash::make($password)])->save();
        }

        Auth::guard('web')->login($user);

        $request->session()->regenerate();

        return response()->noContent();
    }
}
