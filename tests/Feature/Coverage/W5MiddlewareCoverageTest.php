<?php

declare(strict_types=1);

namespace Tests\Feature\Coverage;

use App\Http\Middleware\ApplyApiSecurityHeaders;
use App\Http\Middleware\CorrelateRequest;
use App\Http\Middleware\DisallowHeaderAuthentication;
use App\Http\Middleware\EnsureAccountNotDeactivated;
use App\Http\Middleware\EnsureStatefulSession;
use App\Support\ApiMessage;
use App\Support\Correlation;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Guarda final dos cinco middlewares: se o pipeline devolver algo que não é
 * Response, o middleware falha em vez de propagar o valor. O Kernel sempre
 * devolve Response, então a condição só é alcançável invocando handle() com um
 * $next que viole o contrato — daí a chamada direta, com controle positivo.
 */
final class W5MiddlewareCoverageTest extends TestCase
{
    public function test_security_headers_middleware_fails_when_the_pipeline_returns_a_non_response(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The request pipeline must return a response.');

        (new ApplyApiSecurityHeaders)->handle(
            Request::create('/api/v1/health', 'GET'),
            static fn (): string => 'not-a-response',
        );
    }

    public function test_security_headers_middleware_applies_headers_to_api_responses(): void
    {
        $response = (new ApplyApiSecurityHeaders)->handle(
            Request::create('/api/v1/health', 'GET'),
            static fn (): Response => new Response('ok'),
        );

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertSame(
            "default-src 'none'; frame-ancestors 'none'",
            $response->headers->get('Content-Security-Policy'),
        );
    }

    public function test_correlation_middleware_fails_when_the_pipeline_returns_a_non_response(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The request pipeline must return a response.');

        (new CorrelateRequest)->handle(
            Request::create('/api/v1/health', 'GET'),
            static fn (): array => [],
        );
    }

    public function test_correlation_middleware_echoes_an_incoming_uuid_and_generates_one_when_absent(): void
    {
        $incoming = (string) Str::uuid();
        $withHeader = Request::create('/api/v1/health', 'GET');
        $withHeader->headers->set(Correlation::HEADER, $incoming);

        $echoed = (new CorrelateRequest)->handle(
            $withHeader,
            static fn (): Response => new Response('ok'),
        );

        $this->assertSame($incoming, $echoed->headers->get(Correlation::HEADER));

        $generated = (new CorrelateRequest)->handle(
            Request::create('/api/v1/health', 'GET'),
            static fn (): Response => new Response('ok'),
        );

        $this->assertTrue(Str::isUuid((string) $generated->headers->get(Correlation::HEADER)));
    }

    public function test_header_authentication_middleware_fails_when_the_pipeline_returns_a_non_response(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The request pipeline must return a response.');

        (new DisallowHeaderAuthentication)->handle(
            Request::create('/api/v1/health', 'GET'),
            static fn (): string => 'not-a-response',
        );
    }

    public function test_header_authentication_is_rejected_while_session_requests_pass(): void
    {
        $middleware = new DisallowHeaderAuthentication;

        $withHeader = Request::create('/api/v1/health', 'GET');
        $withHeader->headers->set('Authorization', 'Bearer synthetic-token');

        try {
            $middleware->handle($withHeader, static fn (): Response => new Response('ok'));
            $this->fail('Header authentication must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(Response::HTTP_UNAUTHORIZED, $exception->getStatusCode());
            $this->assertSame(ApiMessage::UNAUTHENTICATED, $exception->getMessage());
        }

        $sessionRequest = $middleware->handle(
            Request::create('/api/v1/health', 'GET'),
            static fn (): Response => new Response('ok'),
        );

        $this->assertSame(Response::HTTP_OK, $sessionRequest->getStatusCode());
    }

    public function test_deactivated_account_middleware_fails_when_the_pipeline_returns_a_non_response(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The request pipeline must return a response.');

        (new EnsureAccountNotDeactivated)->handle(
            Request::create('/api/v1/health', 'GET'),
            static fn (): string => 'not-a-response',
        );
    }

    public function test_deactivated_account_middleware_passes_anonymous_requests(): void
    {
        $response = (new EnsureAccountNotDeactivated)->handle(
            Request::create('/api/v1/health', 'GET'),
            static fn (): Response => new Response('ok'),
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function test_stateful_session_middleware_fails_when_the_pipeline_returns_a_non_response(): void
    {
        $session = $this->app->make('session.store');
        $this->assertInstanceOf(Store::class, $session);

        $request = Request::create('/api/v1/health', 'GET');
        $request->setLaravelSession($session);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The request pipeline must return a response.');

        (new EnsureStatefulSession)->handle($request, static fn (): string => 'not-a-response');
    }

    public function test_stateful_session_middleware_passes_requests_that_carry_a_session(): void
    {
        $session = $this->app->make('session.store');
        $this->assertInstanceOf(Store::class, $session);

        $request = Request::create('/api/v1/health', 'GET');
        $request->setLaravelSession($session);

        $response = (new EnsureStatefulSession)->handle(
            $request,
            static fn (): Response => new Response('ok'),
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
