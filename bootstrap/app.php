<?php

declare(strict_types=1);

use App\Http\Middleware\ApplyApiSecurityHeaders;
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
use Symfony\Component\HttpFoundation\Response;
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
        $middleware->append(ApplyApiSecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            return app(ApplyApiSecurityHeaders::class)->apply($request, $response);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request): ?JsonResponse {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return response()->json(['message' => ApiMessage::UNAUTHENTICATED], Response::HTTP_UNAUTHORIZED);
        });

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

            return response()->json(['message' => ApiMessage::SERVER_ERROR], Response::HTTP_INTERNAL_SERVER_ERROR);
        });
    })->create();
