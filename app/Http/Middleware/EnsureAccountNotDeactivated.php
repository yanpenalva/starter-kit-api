<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAccountNotDeactivated
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->deactivated_at !== null) {
            abort(403, 'This account has been deactivated.');
        }

        $response = $next($request);

        if (! $response instanceof Response) {
            throw new RuntimeException('The request pipeline must return a response.');
        }

        return $response;
    }
}
