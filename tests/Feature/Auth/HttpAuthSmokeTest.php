<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Real HTTP cookie-jar smoke test against an ephemeral single-process php -S instance
 * running with APP_ENV=local. Verifies real browser-like session cookies, SameSite,
 * CSRF token enforcement (419 on missing/invalid), session fixation prevention,
 * credentialed CORS, and logout invalidation over actual HTTP sockets.
 */
final class HttpAuthSmokeTest extends TestCase
{
    private const SERVER_HOST = '127.0.0.1';

    private const SERVER_PORT = '8099';

    private const SMOKE_EMAIL = 'smoke.user@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        $database = config('database.connections.pgsql.database');
        $this->assertIsString($database);
        $this->assertSame('starter_api_test', $database);

        User::query()->where('email', self::SMOKE_EMAIL)->delete();
    }

    protected function tearDown(): void
    {
        User::query()->where('email', self::SMOKE_EMAIL)->delete();

        parent::tearDown();
    }

    public function test_real_http_session_cookie_csrf_fixation_and_cors_lifecycle(): void
    {
        $user = User::factory()->create([
            'email' => 'smoke.user@example.test',
            'password' => 'secret123',
        ]);

        $environment = $this->serverEnvironment();
        $server = $this->startServer(self::SERVER_PORT, $environment);

        try {
            $jar = new CookieJar;
            $client = $this->waitUntilServerIsUp($this->createClient($jar), $server);

            // 1. GET /sanctum/csrf-cookie
            $csrfResponse = $client->get('/sanctum/csrf-cookie', [
                'headers' => ['Origin' => 'http://localhost:5173'],
            ]);
            $this->assertSame(204, $csrfResponse->getStatusCode());

            $xsrfCookie = $jar->getCookieByName('XSRF-TOKEN');
            $this->assertInstanceOf(SetCookie::class, $xsrfCookie, 'XSRF-TOKEN cookie must be set');
            $xsrfToken = rawurldecode((string) $xsrfCookie->getValue());

            $sessionCookie = $this->findSessionCookie($jar);
            $this->assertInstanceOf(SetCookie::class, $sessionCookie, 'Session cookie must be set');
            $this->assertTrue($sessionCookie->getHttpOnly(), 'Session cookie must be HttpOnly');
            $initialSessionId = (string) $sessionCookie->getValue();

            // 2. Negative CSRF: POST /login without CSRF token returns 419
            $missingCsrfResponse = $client->post('/api/v1/login', [
                'headers' => [
                    'Origin' => 'http://localhost:5173',
                    'Referer' => 'http://localhost:5173/',
                ],
                'json' => [
                    'email' => 'smoke.user@example.test',
                    'password' => 'secret123',
                ],
            ]);
            $this->assertSame(419, $missingCsrfResponse->getStatusCode(), 'Missing CSRF must return 419');

            // 3. Negative CSRF: POST /login with invalid CSRF token returns 419
            $invalidCsrfResponse = $client->post('/api/v1/login', [
                'headers' => [
                    'Origin' => 'http://localhost:5173',
                    'Referer' => 'http://localhost:5173/',
                    'X-XSRF-TOKEN' => 'forged-or-invalid-token',
                ],
                'json' => [
                    'email' => 'smoke.user@example.test',
                    'password' => 'secret123',
                ],
            ]);
            $this->assertSame(419, $invalidCsrfResponse->getStatusCode(), 'Invalid CSRF must return 419');

            // 4. Denied CORS: unauthorized origin receives no CORS approval
            $deniedOriginResponse = $client->post('/api/v1/login', [
                'headers' => [
                    'Origin' => 'http://evil.example',
                    'Referer' => 'http://evil.example/',
                    'X-XSRF-TOKEN' => $xsrfToken,
                ],
                'json' => [
                    'email' => 'smoke.user@example.test',
                    'password' => 'secret123',
                ],
            ]);
            $this->assertNotSame(
                'http://evil.example',
                $deniedOriginResponse->getHeaderLine('Access-Control-Allow-Origin'),
            );

            // 5. Successful login with real cookie jar and valid XSRF token
            $loginResponse = $client->post('/api/v1/login', [
                'headers' => [
                    'Origin' => 'http://localhost:5173',
                    'Referer' => 'http://localhost:5173/',
                    'X-XSRF-TOKEN' => $xsrfToken,
                ],
                'json' => [
                    'email' => 'smoke.user@example.test',
                    'password' => 'secret123',
                ],
            ]);
            $this->assertSame(204, $loginResponse->getStatusCode());
            $this->assertSame('http://localhost:5173', $loginResponse->getHeaderLine('Access-Control-Allow-Origin'));
            $this->assertSame('true', $loginResponse->getHeaderLine('Access-Control-Allow-Credentials'));

            // 6. Session fixation proof: session cookie value changed after login
            $newSessionCookie = $this->findSessionCookie($jar);
            $this->assertInstanceOf(SetCookie::class, $newSessionCookie);
            $newSessionId = (string) $newSessionCookie->getValue();
            $this->assertNotSame(
                $initialSessionId,
                $newSessionId,
                'Session ID must be regenerated on login to prevent fixation',
            );

            // 7. GET /api/v1/auth/my-profile with authenticated cookie jar
            $profileResponse = $client->get('/api/v1/auth/my-profile', [
                'headers' => [
                    'Origin' => 'http://localhost:5173',
                    'Referer' => 'http://localhost:5173/',
                ],
            ]);
            $this->assertSame(200, $profileResponse->getStatusCode());
            /** @var array{data: array{id: int, email: string}} $profileData */
            $profileData = json_decode((string) $profileResponse->getBody(), true);
            $this->assertSame($user->id, $profileData['data']['id']);
            $this->assertSame('smoke.user@example.test', $profileData['data']['email']);

            // 8. POST /api/v1/logout with active session and fresh XSRF token
            $latestXsrfCookie = $jar->getCookieByName('XSRF-TOKEN');
            $latestXsrfToken = $latestXsrfCookie instanceof SetCookie
                ? rawurldecode((string) $latestXsrfCookie->getValue())
                : $xsrfToken;

            $logoutResponse = $client->post('/api/v1/logout', [
                'headers' => [
                    'Origin' => 'http://localhost:5173',
                    'Referer' => 'http://localhost:5173/',
                    'X-XSRF-TOKEN' => $latestXsrfToken,
                ],
            ]);
            $this->assertSame(204, $logoutResponse->getStatusCode());

            // 9. Subsequent profile request with prior cookie now returns 401
            $staleProfileResponse = $client->get('/api/v1/auth/my-profile', [
                'headers' => [
                    'Origin' => 'http://localhost:5173',
                    'Referer' => 'http://localhost:5173/',
                ],
            ]);
            $this->assertSame(401, $staleProfileResponse->getStatusCode(), 'Profile after logout must return 401');
        } finally {
            $server->stop(0);
        }
    }

    /**
     * @param  array<string, string>  $environment
     */
    private function startServer(string $port, array $environment): Process
    {
        $server = new Process(
            [
                PHP_BINARY,
                '-S',
                self::SERVER_HOST.':'.$port,
                base_path('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'),
            ],
            cwd: base_path('public'),
            env: $environment,
        );
        $server->setTimeout(120);
        $server->start();

        return $server;
    }

    private function findSessionCookie(CookieJar $jar): ?SetCookie
    {
        /** @var array<int, mixed> $cookies */
        $cookies = $jar->toArray();
        foreach ($cookies as $cookieArray) {
            if (! is_array($cookieArray)) {
                continue;
            }
            $rawName = $cookieArray['Name'] ?? null;
            if (! is_string($rawName)) {
                continue;
            }
            if (str_ends_with($rawName, '-session') || str_contains($rawName, 'session')) {
                return $jar->getCookieByName($rawName);
            }
        }

        return null;
    }

    private function createClient(CookieJar $jar): Client
    {
        return new Client([
            'base_uri' => 'http://'.self::SERVER_HOST.':'.self::SERVER_PORT,
            'cookies' => $jar,
            'http_errors' => false,
            'timeout' => 5,
        ]);
    }

    private function waitUntilServerIsUp(Client $client, Process $server): Client
    {
        $deadline = microtime(true) + 15;

        while (microtime(true) < $deadline) {
            try {
                $response = $client->get('/up');

                if ($response->getStatusCode() === 200) {
                    return $client;
                }
            } catch (\Throwable) {
                usleep(200_000);
            }
        }

        self::fail('The smoke server did not answer /up in time. Server output: ['
            .$server->getOutput().$server->getErrorOutput().']');
    }

    /**
     * @return array<string, string>
     */
    private function serverEnvironment(): array
    {
        $inherited = static fn (string $key): string => (string) getenv($key);

        return [
            'APP_ENV' => 'local',
            'APP_DEBUG' => 'false',
            'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'APP_URL' => 'http://'.self::SERVER_HOST.':'.self::SERVER_PORT,
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => $inherited('DB_HOST') ?: 'postgres-test',
            'DB_PORT' => $inherited('DB_PORT') ?: '5432',
            'DB_DATABASE' => 'starter_api_test',
            'DB_USERNAME' => 'starter_api_test',
            'DB_PASSWORD' => $inherited('DB_PASSWORD'),
            'SESSION_DRIVER' => 'file',
            'SESSION_SECURE_COOKIE' => 'false',
            'CACHE_STORE' => 'file',
        ];
    }
}
