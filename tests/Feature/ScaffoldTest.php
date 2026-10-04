<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

final class ScaffoldTest extends TestCase
{
    public function test_api_availability_and_root_route(): void
    {
        $this->getJson('/api/v1')->assertOk()->assertExactJson(['status' => 'ok']);
        $this->get('/')->assertNotFound();
    }

    public function test_framework_health_check(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_api_errors_are_json_without_stack_details(): void
    {
        $this->get('/api/v1/missing')->assertNotFound()
            ->assertExactJson(['message' => 'Recurso não encontrado.'])
            ->assertHeader('content-type', 'application/json')
            ->assertJsonMissingPath('trace')
            ->assertJsonMissingPath('exception');
    }

    public function test_method_not_allowed_renders_localized_405_body(): void
    {
        $this->postJson('/api/v1')
            ->assertMethodNotAllowed()
            ->assertExactJson(['message' => 'Método não permitido.']);
    }

    public function test_authorization_denial_renders_generic_localized_403(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);
        $request = Request::create('/api/v1/auth/my-profile');

        $response = $handler->render($request, new AuthorizationException('This action is unauthorized.'));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(['message' => 'Acesso não autorizado.'], $response->getData(true));
    }

    public function test_unexpected_errors_render_generic_localized_500_without_leaks(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);
        $request = Request::create('/api/v1');

        $response = $handler->render($request, new RuntimeException('secret internal detail /var/www/html'));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(['message' => 'Erro interno do servidor.'], $response->getData(true));

        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('secret internal detail', $body);
        $this->assertStringNotContainsString('/var/www', $body);
        $this->assertStringNotContainsString('trace', $body);
    }

    public function test_postgresql_connection_uses_an_isolated_test_database(): void
    {
        $this->assertSame('pgsql', config('database.default'));

        $identity = (array) DB::selectOne('select current_database() as database, current_user as username');
        $this->assertSame('starter_api_test', $identity['database'] ?? null);
        $this->assertSame('starter_api_test', $identity['username'] ?? null);

        $developmentDatabaseExists = DB::selectOne(
            "select exists(select 1 from pg_database where datname = 'starter_api_dev') as exists",
        );
        $developmentDatabaseExists = (array) $developmentDatabaseExists;
        $this->assertArrayHasKey('exists', $developmentDatabaseExists);
        $this->assertFalse((bool) $developmentDatabaseExists['exists']);
    }

    public function test_only_the_public_surface_routes_are_registered(): void
    {
        $uris = array_map(
            static fn (RouteDefinition $route): string => $route->uri(),
            Route::getRoutes()->getRoutes(),
        );
        sort($uris);

        $this->assertSame([
            'api/v1',
            'api/v1/auth/my-profile',
            'api/v1/login',
            'api/v1/logout',
            'docs/api',
            'docs/api.json',
            'sanctum/csrf-cookie',
            'up',
        ], $uris);
    }
}
