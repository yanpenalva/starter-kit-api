<?php

declare(strict_types=1);

use App\Http\Middleware\CorrelateRequest;
use App\Http\Middleware\DisallowHeaderAuthentication;
use App\Support\ApiMessage;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustHosts(subdomains: false);

        $middleware->redirectGuestsTo(fn (): ?string => null);

        $middleware->statefulApi();
        $middleware->throttleApi();

        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: DisallowHeaderAuthentication::class,
        );

        $middleware->prepend(CorrelateRequest::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request): ?JsonResponse {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return response()->json(['message' => ApiMessage::UNAUTHENTICATED], 401);
        });

        // HTTP exceptions flow through ApiMessage::forHttpException: the
        // application's own abort() texts (owned constants) pass verbatim,
        // while upstream framework defaults ("This action is unauthorized.",
        // "CSRF token mismatch.", model-binding details) are replaced by the
        // localized status message so English internals never reach bodies.
        // ValidationException and HttpResponseException own their rendering;
        // debug mode and non-JSON contexts fall through to the framework.
        // Rate-limit headers (Retry-After, X-RateLimit-*) are machine data
        // and are preserved alongside the localized message.
        $exceptions->render(function (Throwable $e, Request $request): ?JsonResponse {
            if ((bool) config('app.debug')
                || $e instanceof ValidationException
                || $e instanceof HttpResponseException
                || ! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface) {
                return response()->json(
                    ['message' => ApiMessage::forHttpException($e)],
                    $e->getStatusCode(),
                    $e->getHeaders(),
                );
            }

            return response()->json(['message' => ApiMessage::SERVER_ERROR], 500);
        });
    })->create();
