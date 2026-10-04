<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiMessage;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class EnsureStatefulSession
{
    public function handle(Request $request, Closure $next): Response
    {
        // Enforces that stateful session pipeline ran for this request.
        // Bypassing stateful Origin/Referer is rejected with 419 instead of crashing or skipping CSRF.
        if (! $request->hasSession()) {
            abort(419, ApiMessage::CSRF);
        }

        $response = $next($request);

        if (! $response instanceof Response) {
            throw new RuntimeException('The request pipeline must return a response.');
        }

        return $response;
    }
}
