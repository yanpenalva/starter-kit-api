<?php

declare(strict_types=1);

namespace Tests\Feature\Contract;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class OpenApiSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_generates_real_session_contract(): void
    {
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

        /** @var array<string, mixed> $paths */
        $paths = $document['paths'];
        $this->assertArrayHasKey('/login', $paths);
        $this->assertArrayHasKey('/logout', $paths);
        $this->assertArrayHasKey('/auth/my-profile', $paths);
        $this->assertArrayHasKey('/sanctum/csrf-cookie', $paths);

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
        $loginRoute = $paths['/login'];
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

        /** @var mixed $profileRoute */
        $profileRoute = $paths['/auth/my-profile'];
        $this->assertIsArray($profileRoute);
        /** @var mixed $profileGetMixed */
        $profileGetMixed = $profileRoute['get'];
        $this->assertIsArray($profileGetMixed);
        /** @var array<string, mixed> $profileGet */
        $profileGet = $profileGetMixed;
        /** @var array<int, mixed> $profileSecurity */
        $profileSecurity = $profileGet['security'];
        $this->assertSame([['sessionCookie' => []]], $profileSecurity);
    }
}
