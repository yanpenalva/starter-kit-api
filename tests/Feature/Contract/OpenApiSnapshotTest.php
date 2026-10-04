<?php

declare(strict_types=1);

namespace Tests\Feature\Contract;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class OpenApiSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_generates_real_session_contract(): void
    {
        config(['app.url' => 'http://unexpected-custom-origin.test:9999']);
        $output = storage_path('app/openapi-snapshot-test.json');

        $exit = Artisan::call('scramble:export', ['--path' => $output]);
        $this->assertSame(0, $exit);
        $this->assertFileExists($output);

        /** @var string $payload */
        $payload = file_get_contents($output);
        /** @var mixed $decoded */
        $decoded = json_decode($payload, true);
        $this->assertIsArray($decoded);
        /** @var array<string, mixed> $document */
        $document = $decoded;

        $this->assertSame('3.1.0', $document['openapi']);

        /** @var array<string, mixed> $info */
        $info = $document['info'];
        $this->assertSame('1.0.0', $info['version']);

        /** @var list<array{url: string}> $servers */
        $servers = $document['servers'];
        $this->assertSame([['url' => '/']], $servers);
        $this->assertStringNotContainsString('unexpected-custom-origin', $payload);
        $this->assertStringNotContainsString('localhost:8080', $payload);

        /** @var array<string, mixed> $paths */
        $paths = $document['paths'];
        $this->assertArrayHasKey('/api/v1', $paths);
        $this->assertArrayHasKey('/api/v1/login', $paths);
        $this->assertArrayHasKey('/api/v1/logout', $paths);
        $this->assertArrayHasKey('/api/v1/auth/my-profile', $paths);
        $this->assertArrayHasKey('/sanctum/csrf-cookie', $paths);

        // URL resolution of generated operations against root-relative server
        $registeredUris = array_map(
            fn (\Illuminate\Routing\Route $route): string => '/'.ltrim($route->uri(), '/'),
            Route::getRoutes()->getRoutes(),
        );

        $serverUrl = $servers[0]['url'];
        foreach (['/api/v1', '/api/v1/login', '/api/v1/logout', '/api/v1/auth/my-profile', '/sanctum/csrf-cookie'] as $expectedPath) {
            $resolvedUrl = '/'.ltrim($serverUrl, '/').ltrim($expectedPath, '/');
            $this->assertSame($expectedPath, $resolvedUrl);
            $this->assertContains($resolvedUrl, $registeredUris);
        }

        /** @var array<string, mixed> $csrfRoute */
        $csrfRoute = $paths['/sanctum/csrf-cookie'];
        /** @var array<string, mixed> $csrfGet */
        $csrfGet = $csrfRoute['get'];
        /** @var array<string, mixed> $csrfResponses */
        $csrfResponses = $csrfGet['responses'];
        $this->assertArrayHasKey('204', $csrfResponses);
        $this->assertArrayNotHasKey('429', $csrfResponses, 'Non-throttled CSRF cookie route must not declare 429');

        /** @var array<string, mixed> $components */
        $components = $document['components'];
        /** @var array<string, mixed> $schemes */
        $schemes = $components['securitySchemes'];
        $this->assertArrayHasKey('sessionCookie', $schemes);
        $this->assertArrayHasKey('xsrfToken', $schemes);
        $this->assertArrayNotHasKey('bearer', $schemes);

        /** @var array<string, mixed> $sessionScheme */
        $sessionScheme = $schemes['sessionCookie'];
        $this->assertSame('apiKey', $sessionScheme['type']);
        $this->assertSame('cookie', $sessionScheme['in']);
        $this->assertSame('starter-kit-api-session', $sessionScheme['name']);

        $encoded = json_encode($document);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('"bearer"', strtolower($encoded));
        $this->assertStringNotContainsString('personal_access_tokens', $encoded);

        /** @var array<string, mixed> $schemas */
        $schemas = $components['schemas'];
        /** @var array<string, mixed> $profile */
        $profile = $schemas['MyProfileResource'];
        /** @var array<string, mixed> $properties */
        $properties = $profile['properties'];
        $this->assertSame(['id', 'name', 'email', 'email_verified_at', 'roles', 'permissions'], $profile['required']);
        /** @var array<string, mixed> $roles */
        $roles = $properties['roles'];
        $this->assertSame('array', $roles['type']);
        /** @var array<string, mixed> $roleItems */
        $roleItems = $roles['items'];
        $this->assertSame('string', $roleItems['type']);

        /** @var mixed $loginRoute */
        $loginRoute = $paths['/api/v1/login'];
        $this->assertIsArray($loginRoute);
        /** @var mixed $loginPostMixed */
        $loginPostMixed = $loginRoute['post'];
        $this->assertIsArray($loginPostMixed);
        /** @var array<string, mixed> $loginPost */
        $loginPost = $loginPostMixed;
        $this->assertSame([], $loginPost['security']);
        /** @var array<string, mixed> $loginResponses */
        $loginResponses = $loginPost['responses'];
        foreach (['204', '401', '403', '419', '422', '429'] as $status) {
            $this->assertArrayHasKey($status, $loginResponses, "login missing {$status}");
        }

        /** @var mixed $logoutRoute */
        $logoutRoute = $paths['/api/v1/logout'];
        $this->assertIsArray($logoutRoute);
        /** @var array<string, mixed> $logoutPost */
        $logoutPost = $logoutRoute['post'];
        /** @var array<string, mixed> $logoutResponses */
        $logoutResponses = $logoutPost['responses'];
        $this->assertArrayHasKey('429', $logoutResponses, 'Throttled logout route must declare 429');

        /** @var mixed $profileRoute */
        $profileRoute = $paths['/api/v1/auth/my-profile'];
        $this->assertIsArray($profileRoute);
        /** @var mixed $profileGetMixed */
        $profileGetMixed = $profileRoute['get'];
        $this->assertIsArray($profileGetMixed);
        /** @var array<string, mixed> $profileGet */
        $profileGet = $profileGetMixed;
        /** @var array<int, mixed> $profileSecurity */
        $profileSecurity = $profileGet['security'];
        $this->assertSame([['sessionCookie' => []]], $profileSecurity);
        /** @var array<string, mixed> $profileResponses */
        $profileResponses = $profileGet['responses'];
        $this->assertArrayHasKey('429', $profileResponses, 'Throttled profile route must declare 429');

        unlink($output);
    }
}
