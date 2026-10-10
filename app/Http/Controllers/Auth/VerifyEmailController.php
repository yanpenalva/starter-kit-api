<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class VerifyEmailController
{
    public function __invoke(Request $request): RedirectResponse|JsonResponse
    {
        $routeId = $request->route('id');

        if (! is_numeric($routeId)) {
            abort(404);
        }

        $user = User::findOrFail((int) $routeId);

        abort_unless($request->user() instanceof User && $request->user()->is($user), 403);

        if (! is_string($request->route('hash'))
            || ! hash_equals(hash('sha256', $user->email), $request->route('hash'))) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            event(new Verified($user));
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Email verificado com sucesso.']);
        }

        return redirect()->to(self::frontendVerifyUrl());
    }

    public static function frontendVerifyUrl(): string
    {
        $base = config()->string('app.frontend_url', 'http://localhost:5173');

        if (str_contains($base, 'verify-email')) {
            return $base;
        }

        return rtrim($base, '/').'/verify-email?ok=1';
    }
}
