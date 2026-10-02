<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
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
            ->assertHeader('content-type', 'application/json')
            ->assertJsonMissingPath('trace')
            ->assertJsonMissingPath('exception');
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

    public function test_only_the_scaffold_api_and_health_routes_are_registered(): void
    {
        $uris = array_map(
            static fn (RouteDefinition $route): string => $route->uri(),
            Route::getRoutes()->getRoutes(),
        );
        sort($uris);

        $this->assertSame(['api/v1', 'up'], $uris);
    }
}
