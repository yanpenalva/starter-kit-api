<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiMessage;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class DisallowHeaderAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        $authorization = $request->header('Authorization');

        if (is_string($authorization) && trim($authorization) !== '') {
            abort(Response::HTTP_UNAUTHORIZED, ApiMessage::UNAUTHENTICATED);
        }

        $response = $next($request);

        if (! $response instanceof Response) {
            throw new RuntimeException('The request pipeline must return a response.');
        }

        return $response;
    }
}
