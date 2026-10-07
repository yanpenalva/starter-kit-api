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
        if (! $request->hasSession()) {
            abort(ApiMessage::PAGE_EXPIRED, ApiMessage::CSRF);
        }

        $response = $next($request);

        if (! $response instanceof Response) {
            throw new RuntimeException('The request pipeline must return a response.');
        }

        return $response;
    }
}
