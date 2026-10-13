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
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class ScaffoldTest extends TestCase
{
    private const HOSTILE_SENTINEL = 'SENTINEL user@example.test CPF 529.982.247-25 secret-upstream-detail';

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

    public function test_unmapped_statuses_never_leak_upstream_text_and_keep_machine_data(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);
        $request = Request::create('/api/v1/login');

        foreach ([400, 409, 413, 503] as $status) {
            $exception = new HttpException($status, self::HOSTILE_SENTINEL);
            $response = $handler->render($request, $exception);

            $this->assertInstanceOf(JsonResponse::class, $response);
            $this->assertSame($status, $response->getStatusCode(), "status must be preserved for {$status}");
            $this->assertSame(
                ['message' => 'Não foi possível concluir a requisição.'],
                $response->getData(true),
                "body must be the fail-closed generic for {$status}",
            );
            $this->assertStringNotContainsString(self::HOSTILE_SENTINEL, (string) $response->getContent());
        }
    }

    public function test_unmapped_status_preserves_custom_machine_headers(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);
        $request = Request::create('/api/v1');

        $exception = new HttpException(409, self::HOSTILE_SENTINEL, null, ['X-Machine-Code' => 'keep-me']);
        $response = $handler->render($request, $exception);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(409, $response->getStatusCode());
        $this->assertTrue($response->headers->has('X-Machine-Code'));
        $this->assertSame('keep-me', (string) $response->headers->get('X-Machine-Code'));
        $this->assertStringNotContainsString(self::HOSTILE_SENTINEL, (string) $response->getContent());
    }

    public function test_authorization_exception_with_custom_status_maps_fail_closed(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);
        $request = Request::create('/api/v1/auth/my-profile');

        $notFound = (new AuthorizationException(self::HOSTILE_SENTINEL))->asNotFound();
        $response = $handler->render($request, $notFound);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(['message' => 'Recurso não encontrado.'], $response->getData(true));
        $this->assertStringNotContainsString(self::HOSTILE_SENTINEL, (string) $response->getContent());

        $locked = (new AuthorizationException('This action is unauthorized.'))->withStatus(423);
        $response = $handler->render($request, $locked);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(423, $response->getStatusCode());
        $this->assertSame(['message' => 'Não foi possível concluir a requisição.'], $response->getData(true));
    }

    public function test_validation_lang_catalog_covers_every_installed_builtin_rule(): void
    {
        $installed = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        $localized = require lang_path('pt_BR/validation.php');

        $this->assertIsArray($installed);
        $this->assertIsArray($localized);
        $this->assertNotEmpty($localized);

        foreach ($installed as $key => $value) {
            $this->assertArrayHasKey(
                $key,
                $localized,
                "pt_BR validation catalog is missing builtin key [{$key}]",
            );
        }

        foreach ($localized as $key => $value) {
            if (is_array($value) && is_array($installed[$key] ?? null)) {
                foreach ($installed[$key] as $subKey => $subValue) {
                    $this->assertArrayHasKey(
                        $subKey,
                        $value,
                        "pt_BR validation catalog is missing [{$key}.{$subKey}]",
                    );
                }
            }
        }
    }

    public function test_unmapped_validation_rules_never_render_the_raw_english_key(): void
    {
        $installed = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        $this->assertIsArray($installed);

        $newBuiltins = [
            'any_of',
            'array_keys',
            'base64',
            'doesnt_contain',
            'encoding',
            'in_array_keys',
            'prohibited_if_accepted',
            'prohibited_if_declined',
        ];

        foreach ($newBuiltins as $key) {
            $this->assertArrayHasKey($key, $installed, "key [{$key}] must exist in the installed en catalog");
            $this->assertIsString($installed[$key]);
            $this->assertStringContainsString(':attribute', $installed[$key]);

            $line = trans("validation.{$key}");

            $this->assertIsString($line);
            $this->assertNotSame("validation.{$key}", $line, "raw key leaked for [{$key}]");
            $this->assertNotSame('', $line);
            $this->assertStringNotContainsString('The :attribute field', $line);
        }
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
            'api/v1/activity-log',
            'api/v1/auth/my-profile',
            'api/v1/auth/my-profile',
            'api/v1/auth/password',
            'api/v1/auth/sessions',
            'api/v1/auth/sessions',
            'api/v1/auth/sessions/{id}',
            'api/v1/email/verification-notification',
            'api/v1/email/verify/{id}/{hash}',
            'api/v1/forgot-password',
            'api/v1/health',
            'api/v1/login',
            'api/v1/logout',
            'api/v1/register',
            'api/v1/reset-password',
            'api/v1/roles',
            'api/v1/roles',
            'api/v1/roles/{role}',
            'api/v1/roles/{role}',
            'api/v1/roles/{role}',
            'api/v1/roles/{role}/permissions',
            'api/v1/users',
            'api/v1/users',
            'api/v1/users/{user}',
            'api/v1/users/{user}',
            'api/v1/users/{user}',
            'api/v1/users/{user}/roles',
            'api/v1/users/{user}/status',
            'docs/api',
            'docs/api.json',
            'sanctum/csrf-cookie',
            'up',
        ], $uris);

        $profileUpdate = Route::getRoutes()->match(Request::create('/api/v1/auth/my-profile', 'PATCH'));
        $passwordUpdate = Route::getRoutes()->match(Request::create('/api/v1/auth/password', 'PUT'));
        $this->assertSame(['PATCH'], $profileUpdate->methods());
        $this->assertSame(['PUT'], $passwordUpdate->methods());

        $sessionOperations = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/auth/sessions')) {
                continue;
            }

            $methods = array_values(array_filter($route->methods(), 'is_string'));
            $sessionOperations[] = implode('|', $methods).' '.$route->uri();
        }
        sort($sessionOperations);

        $this->assertSame([
            'DELETE api/v1/auth/sessions',
            'DELETE api/v1/auth/sessions/{id}',
            'GET|HEAD api/v1/auth/sessions',
        ], $sessionOperations);
    }
}
