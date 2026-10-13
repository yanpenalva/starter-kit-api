<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\User;
use App\Support\ApiMessage;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class EnsureMfaChallenge
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $hasCompletedChallenge = $user instanceof User
            && $request->hasSession()
            && $request->session()->get('mfa:verified_user_id') === $user->id;

        if ($user instanceof User
            && $user->hasRole(Role::Admin->value)
            && $user->hasMfaEnabled()
            && ! $hasCompletedChallenge) {
            abort(Response::HTTP_UNAUTHORIZED, ApiMessage::UNAUTHENTICATED);
        }

        $response = $next($request);

        if (! $response instanceof Response) {
            throw new RuntimeException('The request pipeline must return a response.');
        }

        return $response;
    }
}
