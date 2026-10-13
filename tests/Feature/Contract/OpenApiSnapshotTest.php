<?php

declare(strict_types=1);

namespace Tests\Feature\Contract;

use App\Models\User;
use App\Support\ApiMessage;
use App\Support\BlockedAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
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
        $this->assertArrayHasKey('/api/v1/health', $paths);
        $this->assertArrayHasKey('/api/v1/login', $paths);
        $this->assertArrayHasKey('/api/v1/logout', $paths);
        $this->assertArrayHasKey('/api/v1/auth/my-profile', $paths);
        $this->assertArrayHasKey('/api/v1/auth/sessions', $paths);
        $this->assertArrayHasKey('/api/v1/auth/sessions/{id}', $paths);
        $this->assertArrayHasKey('/api/v1/register', $paths);
        $this->assertArrayHasKey('/api/v1/forgot-password', $paths);
        $this->assertArrayHasKey('/api/v1/reset-password', $paths);
        $this->assertArrayHasKey('/api/v1/auth/mfa/challenge', $paths);
        $this->assertArrayHasKey('/api/v1/auth/mfa/status', $paths);
        $this->assertArrayHasKey('/api/v1/auth/mfa/enroll', $paths);
        $this->assertArrayHasKey('/api/v1/auth/mfa/confirm', $paths);
        $this->assertArrayHasKey('/api/v1/auth/mfa/disable', $paths);
        $this->assertArrayHasKey('/api/v1/auth/mfa/recovery-codes', $paths);
        $this->assertArrayHasKey('/api/v1/email/verify/{id}/{hash}', $paths);
        $this->assertArrayHasKey('/api/v1/email/verification-notification', $paths);
        $this->assertArrayHasKey('/sanctum/csrf-cookie', $paths);

        $loginPath = $paths['/api/v1/login'];
        $this->assertIsArray($loginPath);
        $loginPost = $loginPath['post'];
        $this->assertIsArray($loginPost);
        $loginResponses = $loginPost['responses'];
        $this->assertIsArray($loginResponses);
        $loginSuccess = $loginResponses['200'];
        $this->assertIsArray($loginSuccess);
        $loginContent = $loginSuccess['content'];
        $this->assertIsArray($loginContent);
        $loginJson = $loginContent['application/json'];
        $this->assertIsArray($loginJson);
        $loginSchema = $loginJson['schema'];
        $this->assertIsArray($loginSchema);
        $loginProperties = $loginSchema['properties'];
        $this->assertIsArray($loginProperties);
        $requiresMfa = $loginProperties['requires_mfa'];
        $this->assertIsArray($requiresMfa);
        $this->assertSame(
            'boolean',
            $requiresMfa['type'],
        );

        $mfaStatusPath = $paths['/api/v1/auth/mfa/status'];
        $this->assertIsArray($mfaStatusPath);
        $mfaStatusGet = $mfaStatusPath['get'];
        $this->assertIsArray($mfaStatusGet);
        $mfaStatusResponses = $mfaStatusGet['responses'];
        $this->assertIsArray($mfaStatusResponses);
        $mfaStatusSuccess = $mfaStatusResponses['200'];
        $this->assertIsArray($mfaStatusSuccess);
        $mfaStatusContent = $mfaStatusSuccess['content'];
        $this->assertIsArray($mfaStatusContent);
        $mfaStatusJson = $mfaStatusContent['application/json'];
        $this->assertIsArray($mfaStatusJson);
        $mfaStatusSchema = $mfaStatusJson['schema'];
        $this->assertIsArray($mfaStatusSchema);
        $mfaStatusProperties = $mfaStatusSchema['properties'];
        $this->assertIsArray($mfaStatusProperties);
        $mfaEnabled = $mfaStatusProperties['mfa_enabled'];
        $this->assertIsArray($mfaEnabled);
        $this->assertSame(
            'boolean',
            $mfaEnabled['type'],
        );

        $registeredUris = array_map(
            fn (\Illuminate\Routing\Route $route): string => '/'.ltrim($route->uri(), '/'),
            Route::getRoutes()->getRoutes(),
        );

        $serverUrl = $servers[0]['url'];
        foreach (['/api/v1', '/api/v1/health', '/api/v1/login', '/api/v1/logout', '/api/v1/auth/my-profile', '/api/v1/auth/sessions', '/api/v1/auth/sessions/{id}', '/sanctum/csrf-cookie'] as $expectedPath) {
            $resolvedUrl = '/'.ltrim($serverUrl, '/').ltrim($expectedPath, '/');
            $this->assertSame($expectedPath, $resolvedUrl);
            $this->assertContains($resolvedUrl, $registeredUris);
        }

        /** @var array<string, mixed> $healthPath */
        $healthPath = $paths['/api/v1/health'];
        /** @var array<string, mixed> $healthGet */
        $healthGet = $healthPath['get'];
        $this->assertSame([], $healthGet['security']);

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

        /** @var list<array{path: string, method: string, responses: list<string>}> $sessionOperations */
        $sessionOperations = [
            ['path' => '/api/v1/auth/sessions', 'method' => 'get', 'responses' => ['200', '401', '403', '429']],
            ['path' => '/api/v1/auth/sessions', 'method' => 'delete', 'responses' => ['204', '401', '403', '419', '429']],
            ['path' => '/api/v1/auth/sessions/{id}', 'method' => 'delete', 'responses' => ['204', '401', '403', '404', '419', '429']],
        ];
        foreach ($sessionOperations as $sessionOperation) {
            /** @var array<string, mixed> $sessionPath */
            $sessionPath = $paths[$sessionOperation['path']];
            /** @var array<string, mixed> $operation */
            $operation = $sessionPath[$sessionOperation['method']];
            /** @var array<int, mixed> $security */
            $security = $operation['security'];
            $this->assertSame([['sessionCookie' => []]], $security);
            /** @var array<string, mixed> $responses */
            $responses = $operation['responses'];
            foreach ($sessionOperation['responses'] as $status) {
                $this->assertArrayHasKey($status, $responses, $sessionOperation['method'].' '.$sessionOperation['path'].' missing '.$status);
            }
            if ($sessionOperation['method'] === 'delete') {
                /** @var list<array{name: string, in: string, required: bool}> $parameters */
                $parameters = $operation['parameters'];
                $xsrf = array_values(array_filter($parameters, static fn (array $parameter): bool => $parameter['name'] === 'X-XSRF-TOKEN'));
                $this->assertCount(1, $xsrf);
                $this->assertSame('header', $xsrf[0]['in']);
                $this->assertTrue($xsrf[0]['required']);
            }
        }

        /** @var array<string, mixed> $sessionsPath */
        $sessionsPath = $paths['/api/v1/auth/sessions'];
        $sessionsGet = $sessionsPath['get'];
        $this->assertIsArray($sessionsGet);
        $sessionsResponses = $sessionsGet['responses'];
        $this->assertIsArray($sessionsResponses);
        $sessionsSuccess = $sessionsResponses['200'];
        $this->assertIsArray($sessionsSuccess);
        $sessionsContent = $sessionsSuccess['content'];
        $this->assertIsArray($sessionsContent);
        $sessionsJson = $sessionsContent['application/json'];
        $this->assertIsArray($sessionsJson);
        $sessionsSchema = $sessionsJson['schema'];
        $this->assertIsArray($sessionsSchema);
        $sessionsProperties = $sessionsSchema['properties'];
        $this->assertIsArray($sessionsProperties);
        $sessionsData = $sessionsProperties['data'];
        $this->assertIsArray($sessionsData);
        $sessionsItems = $sessionsData['items'];
        $this->assertIsArray($sessionsItems);
        $sessionItemProperties = $sessionsItems['properties'];
        $this->assertIsArray($sessionItemProperties);
        $isCurrent = $sessionItemProperties['is_current'];
        $this->assertIsArray($isCurrent);
        $this->assertSame('boolean', $isCurrent['type']);
        /** @var array<string, mixed> $profileResponses */
        $profileResponses = $profileGet['responses'];
        $this->assertArrayHasKey('429', $profileResponses, 'Throttled profile route must declare 429');

        $this->assertBlockedResponseSchema($paths, '/api/v1/login', 'post');
        $this->assertBlockedResponseSchema($paths, '/api/v1/auth/my-profile', 'get');

        $this->assertIdentityContract($paths);

        $this->assertMessageExamplesMatchApiMessageConstants($document);

        unlink($output);
    }

    public function test_openapi_export_is_byte_deterministic(): void
    {
        $previousLocale = setlocale(LC_ALL, '0');
        $firstOutput = storage_path('app/openapi-determinism-first.json');
        $secondOutput = storage_path('app/openapi-determinism-second.json');

        setlocale(LC_ALL, 'C');
        config(['app.url' => 'http://deterministic-origin.test']);

        try {
            foreach ([$firstOutput, $secondOutput] as $output) {
                $exit = Artisan::call('scramble:export', ['--path' => $output]);
                $this->assertSame(0, $exit);
                $this->assertFileExists($output);
            }

            $firstPayload = file_get_contents($firstOutput);
            $secondPayload = file_get_contents($secondOutput);

            $this->assertIsString($firstPayload);
            $this->assertIsString($secondPayload);
            $this->assertSame($firstPayload, $secondPayload);
        } finally {
            foreach ([$firstOutput, $secondOutput] as $output) {
                if (is_file($output)) {
                    unlink($output);
                }
            }

            if (is_string($previousLocale)) {
                setlocale(LC_ALL, $previousLocale);
            }
        }
    }

    public function test_contract_examples_match_real_runtime_responses(): void
    {
        $this->assertRuntimeMessage(419, ApiMessage::CSRF, function (): TestResponse {
            return $this->postJson('/api/v1/login', ['email' => 'parity@example.test', 'password' => 'wrong']);
        });

        $this->assertRuntimeMessage(401, ApiMessage::INVALID_CREDENTIALS, function (): TestResponse {
            return $this->withSession([])
                ->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'])
                ->postJson('/api/v1/login', ['email' => 'parity@example.test', 'password' => 'wrong']);
        });

        $email = 'parity-throttled@example.test';
        $key = hash('sha256', $email.'|127.0.0.1');
        RateLimiter::clear($key);
        $blocked = null;
        foreach (range(1, 6) as $attempt) {
            $blocked = $this->withSession([])
                ->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'])
                ->postJson('/api/v1/login', ['email' => $email, 'password' => 'wrong']);
            $blocked->assertStatus($attempt < 6 ? 401 : 429);
        }
        $this->assertInstanceOf(TestResponse::class, $blocked);
        $this->assertSame(ApiMessage::THROTTLED, $blocked->json('message'));
        $this->assertTrue($blocked->headers->has('Retry-After'));
        RateLimiter::clear($key);

        $this->assertRuntimeMessage(401, ApiMessage::UNAUTHENTICATED, function (): TestResponse {
            return $this->withSession([])
                ->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'])
                ->getJson('/api/v1/auth/my-profile');
        });

        $deactivated = User::factory()->deactivated()->create([
            'email' => 'parity-deactivated@example.test',
            'password' => Hash::make('secret123'),
        ]);
        $this->assertRuntimeMessage(403, ApiMessage::DEACTIVATED, function () use ($deactivated): TestResponse {
            return $this->withSession([])
                ->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'])
                ->postJson('/api/v1/login', ['email' => $deactivated->email, 'password' => 'secret123']);
        });

        $this->assertRuntimeMessage(404, ApiMessage::NOT_FOUND, function (): TestResponse {
            return $this->withSession([])
                ->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'])
                ->getJson('/api/v1/parity-nonexistent-endpoint');
        });

        $this->assertRuntimeMessage(405, ApiMessage::METHOD_NOT_ALLOWED, function (): TestResponse {
            return $this->withSession([])
                ->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'])
                ->patchJson('/api/v1');
        });

        $document = $this->exportContractDocument();
        $this->assertMessageExamplesMatchApiMessageConstants($document);
    }

    /**
     * @param  array<string, mixed>  $paths
     */
    private function assertIdentityContract(array $paths): void
    {
        foreach ([
            ['path' => '/api/v1/register', 'method' => 'post', 'security' => []],
            ['path' => '/api/v1/forgot-password', 'method' => 'post', 'security' => []],
            ['path' => '/api/v1/reset-password', 'method' => 'post', 'security' => []],
            ['path' => '/api/v1/email/verify/{id}/{hash}', 'method' => 'get', 'security' => [['sessionCookie' => []]]],
        ] as $entry) {
            /** @var mixed $node */
            $node = $paths[$entry['path']];
            $this->assertIsArray($node);
            /** @var mixed $node */
            $node = $node[$entry['method']];
            $this->assertIsArray($node);
            /** @var array<string, mixed> $operation */
            $operation = $node;
            $this->assertSame($entry['security'], $operation['security'], $entry['path'].' has incorrect security');
            /** @var array<string, mixed> $responses */
            $responses = $operation['responses'];
            $this->assertArrayHasKey('429', $responses, 'Throttled '.$entry['path'].' must declare 429');
        }

        /** @var mixed $register */
        $register = $paths['/api/v1/register'];
        $this->assertIsArray($register);
        /** @var mixed $registerPost */
        $registerPost = $register['post'];
        $this->assertIsArray($registerPost);
        /** @var array<string, mixed> $registerResponses */
        $registerResponses = $registerPost['responses'];
        $this->assertArrayHasKey('419', $registerResponses, 'Stateful register route must declare 419');

        /** @var mixed $send */
        $send = $paths['/api/v1/email/verification-notification'];
        $this->assertIsArray($send);
        /** @var mixed $sendPost */
        $sendPost = $send['post'];
        $this->assertIsArray($sendPost);
        /** @var array<string, mixed> $sendOperation */
        $sendOperation = $sendPost;
        $this->assertSame([['sessionCookie' => []]], $sendOperation['security']);
        /** @var array<string, mixed> $sendResponses */
        $sendResponses = $sendOperation['responses'];
        $this->assertArrayHasKey('401', $sendResponses);
        $this->assertArrayHasKey('429', $sendResponses);

        /** @var mixed $verify */
        $verify = $paths['/api/v1/email/verify/{id}/{hash}'];
        $this->assertIsArray($verify);
        /** @var mixed $verifyGet */
        $verifyGet = $verify['get'];
        $this->assertIsArray($verifyGet);
        /** @var array<string, mixed> $verifyResponses */
        $verifyResponses = $verifyGet['responses'];
        $this->assertArrayHasKey('401', $verifyResponses);
        $this->assertArrayHasKey('302', $verifyResponses);
        /** @var list<array<string, mixed>> $verifyParams */
        $verifyParams = $verifyGet['parameters'] ?? [];
        $queryNames = [];
        foreach ($verifyParams as $param) {
            if (($param['in'] ?? null) === 'query' && isset($param['name'])) {
                $queryNames[] = $param['name'];
            }
        }
        $this->assertContains('expires', $queryNames);
        $this->assertContains('signature', $queryNames);
    }

    /**
     * @param  array<string, mixed>  $paths
     */
    private function assertBlockedResponseSchema(array $paths, string $path, string $method): void
    {
        /** @var mixed $node */
        $node = $paths[$path];
        $this->assertIsArray($node);
        /** @var mixed $node */
        $node = $node[$method];
        $this->assertIsArray($node);
        /** @var array<string, mixed> $operation */
        $operation = $node;
        /** @var mixed $node */
        $node = $operation['responses'];
        $this->assertIsArray($node);
        /** @var mixed $node */
        $node = $node['403'];
        $this->assertIsArray($node);
        /** @var array<string, mixed> $forbidden */
        $forbidden = $node;
        /** @var mixed $node */
        $node = $forbidden['content'];
        $this->assertIsArray($node);
        /** @var mixed $node */
        $node = $node['application/json'];
        $this->assertIsArray($node);
        /** @var mixed $node */
        $node = $node['schema'];
        $this->assertIsArray($node);
        /** @var array<string, mixed> $schema */
        $schema = $node;
        $this->assertSame(['message', 'reason', 'email_verified_at'], $schema['required']);
        /** @var mixed $node */
        $node = $schema['properties'];
        $this->assertIsArray($node);
        /** @var array<string, mixed> $properties */
        $properties = $node;
        /** @var mixed $reason */
        $reason = $properties['reason'];
        $this->assertIsArray($reason);
        /** @var array<string, mixed> $reasonSchema */
        $reasonSchema = $reason;
        $this->assertSame(
            [BlockedAccount::REASON_PENDING_APPROVAL, BlockedAccount::REASON_SUSPENDED],
            $reasonSchema['enum']
        );
        /** @var mixed $verifiedAt */
        $verifiedAt = $properties['email_verified_at'];
        $this->assertIsArray($verifiedAt);
        /** @var array<string, mixed> $verifiedAtSchema */
        $verifiedAtSchema = $verifiedAt;
        $this->assertSame('date-time', $verifiedAtSchema['format']);
        /** @var mixed $nullableType */
        $nullableType = $verifiedAtSchema['type'];
        $this->assertIsArray($nullableType);
        /** @var list<string> $typeList */
        $typeList = $nullableType;
        $this->assertContains('string', $typeList);
        $this->assertContains('null', $typeList);
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function assertMessageExamplesMatchApiMessageConstants(array $document): void
    {
        /** @var array<string, mixed> $paths */
        $paths = $document['paths'];

        $this->assertSame(ApiMessage::INVALID_CREDENTIALS, $this->messageExample($paths, '/api/v1/login', 'post', '401'));
        $this->assertSame(ApiMessage::DEACTIVATED, $this->messageExample($paths, '/api/v1/login', 'post', '403'));
        $this->assertSame(ApiMessage::CSRF, $this->messageExample($paths, '/api/v1/login', 'post', '419'));
        $this->assertSame(ApiMessage::THROTTLED, $this->messageExample($paths, '/api/v1/login', 'post', '429'));

        $this->assertSame(ApiMessage::UNAUTHENTICATED, $this->messageExample($paths, '/api/v1/logout', 'post', '401'));
        $this->assertSame(ApiMessage::CSRF, $this->messageExample($paths, '/api/v1/logout', 'post', '419'));
        $this->assertSame(ApiMessage::THROTTLED, $this->messageExample($paths, '/api/v1/logout', 'post', '429'));

        $this->assertSame(ApiMessage::UNAUTHENTICATED, $this->messageExample($paths, '/api/v1/auth/my-profile', 'get', '401'));
        $this->assertSame(ApiMessage::DEACTIVATED, $this->messageExample($paths, '/api/v1/auth/my-profile', 'get', '403'));
        $this->assertSame(ApiMessage::THROTTLED, $this->messageExample($paths, '/api/v1/auth/my-profile', 'get', '429'));

        $this->assertSame(ApiMessage::CSRF, $this->messageExample($paths, '/api/v1/register', 'post', '419'));
        $this->assertSame(ApiMessage::THROTTLED, $this->messageExample($paths, '/api/v1/register', 'post', '429'));

        $this->assertSame(ApiMessage::UNAUTHENTICATED, $this->messageExample($paths, '/api/v1/email/verification-notification', 'post', '401'));
        $this->assertSame(ApiMessage::THROTTLED, $this->messageExample($paths, '/api/v1/email/verification-notification', 'post', '429'));

        $this->assertSame(ApiMessage::UNAUTHENTICATED, $this->messageExample($paths, '/api/v1/email/verify/{id}/{hash}', 'get', '401'));
        $this->assertSame(ApiMessage::THROTTLED, $this->messageExample($paths, '/api/v1/email/verify/{id}/{hash}', 'get', '429'));
        $this->assertSame(ApiMessage::THROTTLED, $this->messageExample($paths, '/api/v1/forgot-password', 'post', '429'));
        $this->assertSame(ApiMessage::THROTTLED, $this->messageExample($paths, '/api/v1/reset-password', 'post', '429'));
    }

    /**
     * @param  callable(): mixed  $request
     */
    private function assertRuntimeMessage(int $status, string $expected, callable $request): void
    {
        $response = $request();
        $this->assertInstanceOf(TestResponse::class, $response);
        $response->assertStatus($status);
        $this->assertSame($expected, $response->json('message'));
    }

    /**
     * @param  array<string, mixed>  $paths
     */
    private function messageExample(array $paths, string $path, string $method, string $status): string
    {
        /** @var mixed $node */
        $node = $paths[$path];
        $this->assertIsArray($node);
        /** @var mixed $node */
        $node = $node[$method];
        $this->assertIsArray($node);
        /** @var array<string, mixed> $operation */
        $operation = $node;
        /** @var mixed $node */
        $node = $operation['responses'];
        $this->assertIsArray($node);
        /** @var mixed $node */
        $node = $node[$status];
        $this->assertIsArray($node);
        /** @var mixed $node */
        $node = $node['content'];
        $this->assertIsArray($node);
        /** @var mixed $node */
        $node = $node['application/json'];
        $this->assertIsArray($node);
        /** @var mixed $node */
        $node = $node['schema'];
        $this->assertIsArray($node);
        /** @var mixed $node */
        $node = $node['properties'];
        $this->assertIsArray($node);
        /** @var mixed $node */
        $node = $node['message'];
        $this->assertIsArray($node);
        /** @var mixed $node */
        $node = $node['examples'];
        $this->assertIsArray($node);
        /** @var list<string> $examples */
        $examples = $node;

        return $examples[0];
    }

    /**
     * @return array<string, mixed>
     */
    private function exportContractDocument(): array
    {
        $output = storage_path('app/openapi-parity-test.json');

        $exit = Artisan::call('scramble:export', ['--path' => $output]);
        $this->assertSame(0, $exit);
        $this->assertFileExists($output);

        /** @var mixed $decoded */
        $decoded = json_decode((string) file_get_contents($output), true);
        $this->assertIsArray($decoded);
        unlink($output);

        /** @var array<string, mixed> $document */
        $document = $decoded;

        return $document;
    }
}
