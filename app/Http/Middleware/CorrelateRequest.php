<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Correlation;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class CorrelateRequest
{
    public function handle(Request $request, Closure $next): Response
    {

        $headerValue = app()->environment('local', 'testing')
            ? $request->headers->get(Correlation::HEADER)
            : null;

        $correlationId = Correlation::fromRequest($headerValue);

        Correlation::use($correlationId);

        $response = $next($request);

        if (! $response instanceof Response) {
            throw new RuntimeException('The request pipeline must return a response.');
        }

        $response->headers->set(Correlation::HEADER, $correlationId);

        return $response;
    }
}
