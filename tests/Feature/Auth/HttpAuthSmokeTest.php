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
 * running with APP_ENV=testing.
 *
 * NOTE: This is a socket-level HTTP and session-cookie boundary test verifying framework
 * middleware, CSRF tokens, SameSite attributes, anti-fixation session migration, and
 * invalidation. It is NOT a full browser/SPA end-to-end test.
 */
final class HttpAuthSmokeTest extends TestCase
{
    private const SERVER_HOST = '127.0.0.1';

    private const SERVER_PORT = '8099';

    private const SMOKE_EMAIL = 'smoke.user@example.test';

    private const DEACTIVATED_EMAIL = 'smoke.deactivated@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertIsolatedTestingRuntime();

        User::query()->whereIn('email', [self::SMOKE_EMAIL, self::DEACTIVATED_EMAIL])->delete();
    }

    protected function tearDown(): void
    {
        $this->assertIsolatedTestingRuntime();

        User::query()->whereIn('email', [self::SMOKE_EMAIL, self::DEACTIVATED_EMAIL])->delete();

        parent::tearDown();
    }

    public function test_real_http_session_cookie_csrf_fixation_and_cors_lifecycle(): void
    {
        $this->assertIsolatedTestingRuntime();

        $user = User::factory()->create([
            'email' => self::SMOKE_EMAIL,
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

            // Prove SameSite attribute on raw response headers
            $rawSetCookies = $csrfResponse->getHeader('Set-Cookie');
            $this->assertNotEmpty($rawSetCookies);
            $hasSameSite = false;
            foreach ($rawSetCookies as $rawCookie) {
                if (preg_match('/samesite=lax/i', $rawCookie) === 1) {
                    $hasSameSite = true;
                    break;
                }
            }
            $this->assertTrue($hasSameSite, 'Cookies emitted by sanctum/csrf-cookie must include SameSite=Lax');

            $xsrfCookie = $jar->getCookieByName('XSRF-TOKEN');
            $this->assertInstanceOf(SetCookie::class, $xsrfCookie, 'XSRF-TOKEN cookie must be set');
            $preLoginXsrfToken = rawurldecode((string) $xsrfCookie->getValue());

            $sessionCookie = $this->findSessionCookie($jar);
            $this->assertInstanceOf(SetCookie::class, $sessionCookie, 'Session cookie must be set');
            $this->assertTrue($sessionCookie->getHttpOnly(), 'Session cookie must be HttpOnly');

            // Snapshot the pre-login cookie jar for the session fixation proof
            $preLoginJarSnapshot = clone $jar;

            // 2. Negative CSRF: POST /login without CSRF token returns 419
            $missingCsrfResponse = $client->post('/api/v1/login', [
                'headers' => [
                    'Origin' => 'http://localhost:5173',
                    'Referer' => 'http://localhost:5173/',
                ],
                'json' => [
                    'email' => self::SMOKE_EMAIL,
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
                    'email' => self::SMOKE_EMAIL,
                    'password' => 'secret123',
                ],
            ]);
            $this->assertSame(419, $invalidCsrfResponse->getStatusCode(), 'Invalid CSRF must return 419');

            // 4. Denied CORS: unauthorized origin receives no CORS approval
            $deniedOriginResponse = $client->post('/api/v1/login', [
                'headers' => [
                    'Origin' => 'http://evil.example',
                    'Referer' => 'http://evil.example/',
                    'X-XSRF-TOKEN' => $preLoginXsrfToken,
                ],
                'json' => [
                    'email' => self::SMOKE_EMAIL,
                    'password' => 'secret123',
                ],
            ]);
            $this->assertNotSame(
                'http://evil.example',
                $deniedOriginResponse->getHeaderLine('Access-Control-Allow-Origin'),
            );

            // 5. Non-stateful bypass rejection: request without stateful origin/referer returns 419
            $nonStatefulResponse = $client->post('/api/v1/login', [
                'headers' => [
                    'Origin' => 'http://unsupported-host.invalid',
                    'X-XSRF-TOKEN' => $preLoginXsrfToken,
                ],
                'json' => [
                    'email' => self::SMOKE_EMAIL,
                    'password' => 'secret123',
                ],
            ]);
            $this->assertSame(419, $nonStatefulResponse->getStatusCode(), 'Non-stateful request must be rejected with 419');

            // 6. Successful login with real cookie jar and valid XSRF token
            $loginResponse = $client->post('/api/v1/login', [
                'headers' => [
                    'Origin' => 'http://localhost:5173',
                    'Referer' => 'http://localhost:5173/',
                    'X-XSRF-TOKEN' => $preLoginXsrfToken,
                ],
                'json' => [
                    'email' => self::SMOKE_EMAIL,
                    'password' => 'secret123',
                ],
            ]);
            $this->assertSame(204, $loginResponse->getStatusCode());
            $this->assertSame('http://localhost:5173', $loginResponse->getHeaderLine('Access-Control-Allow-Origin'));
            $this->assertSame('true', $loginResponse->getHeaderLine('Access-Control-Allow-Credentials'));

            // 7. Genuine session fixation proof: replay pre-login cookie snapshot -> 401; current jar -> 200
            $preLoginReplayClient = $this->createClient($preLoginJarSnapshot);
            $replayedPreLoginResponse = $preLoginReplayClient->get('/api/v1/auth/my-profile', [
                'headers' => [
                    'Origin' => 'http://localhost:5173',
                    'Referer' => 'http://localhost:5173/',
                ],
            ]);
            $this->assertSame(
                401,
                $replayedPreLoginResponse->getStatusCode(),
                'Replaying pre-login session cookie must return 401 unauthenticated (proves session fixation prevention)',
            );

            // 8. GET /api/v1/auth/my-profile with authenticated cookie jar returns 200
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
            $this->assertSame(self::SMOKE_EMAIL, $profileData['data']['email']);

            // 8b. Disallowed Header Authentication: Bearer header is rejected with 401 even with authenticated cookies
            $headerResponse = $client->get('/api/v1/auth/my-profile', [
                'headers' => [
                    'Origin' => 'http://localhost:5173',
                    'Referer' => 'http://localhost:5173/',
                    'Authorization' => 'Bearer token-not-allowed',
                ],
            ]);
            $this->assertSame(401, $headerResponse->getStatusCode(), 'Bearer authorization header must return 401');

            // 9. Existing-session deactivation interception: active session receives 403 once account is deactivated
            User::factory()->create([
                'email' => self::DEACTIVATED_EMAIL,
                'password' => 'secret123',
            ]);
            $deactivatedJar = new CookieJar;
            $deactivatedClient = $this->createClient($deactivatedJar);
            $deactivatedCsrf = $deactivatedClient->get('/sanctum/csrf-cookie', [
                'headers' => ['Origin' => 'http://localhost:5173'],
            ]);
            $this->assertSame(204, $deactivatedCsrf->getStatusCode());
            $deactivatedXsrfCookie = $deactivatedJar->getCookieByName('XSRF-TOKEN');
            $this->assertInstanceOf(SetCookie::class, $deactivatedXsrfCookie);
            $deactivatedXsrfToken = rawurldecode((string) $deactivatedXsrfCookie->getValue());

            $deactivatedLogin = $deactivatedClient->post('/api/v1/login', [
                'headers' => [
                    'Origin' => 'http://localhost:5173',
                    'Referer' => 'http://localhost:5173/',
                    'X-XSRF-TOKEN' => $deactivatedXsrfToken,
                ],
                'json' => [
                    'email' => self::DEACTIVATED_EMAIL,
                    'password' => 'secret123',
                ],
            ]);
            $this->assertSame(204, $deactivatedLogin->getStatusCode());

            // Deactivate account in database while session remains open
            User::query()->where('email', self::DEACTIVATED_EMAIL)->update(['deactivated_at' => now()]);

            $blockedResponse = $deactivatedClient->get('/api/v1/auth/my-profile', [
                'headers' => [
                    'Origin' => 'http://localhost:5173',
                    'Referer' => 'http://localhost:5173/',
                ],
            ]);
            $this->assertSame(403, $blockedResponse->getStatusCode());
            /** @var array{message: string} $blockedBody */
            $blockedBody = json_decode((string) $blockedResponse->getBody(), true);
            $this->assertSame('This account has been deactivated.', $blockedBody['message']);

            // 10. Logout and invalidation proof
            $authenticatedXsrfCookie = $jar->getCookieByName('XSRF-TOKEN');
            $this->assertInstanceOf(SetCookie::class, $authenticatedXsrfCookie);
            $authenticatedXsrfToken = rawurldecode((string) $authenticatedXsrfCookie->getValue());

            // Snapshot the authenticated cookie jar BEFORE logout
            $authenticatedJarSnapshot = clone $jar;

            $logoutResponse = $client->post('/api/v1/logout', [
                'headers' => [
                    'Origin' => 'http://localhost:5173',
                    'Referer' => 'http://localhost:5173/',
                    'X-XSRF-TOKEN' => $authenticatedXsrfToken,
                ],
            ]);
            $this->assertSame(204, $logoutResponse->getStatusCode());

            // Replay the pre-logout authenticated cookie jar -> must return 401 (server-side invalidation)
            $replayedAuthClient = $this->createClient($authenticatedJarSnapshot);
            $staleProfileResponse = $replayedAuthClient->get('/api/v1/auth/my-profile', [
                'headers' => [
                    'Origin' => 'http://localhost:5173',
                    'Referer' => 'http://localhost:5173/',
                ],
            ]);
            $this->assertSame(
                401,
                $staleProfileResponse->getStatusCode(),
                'Replaying pre-logout authenticated session cookie must return 401 (proves session invalidation)',
            );

            // 11. Stale CSRF token rejection: state-changing POST with old CSRF token returns 419
            $staleCsrfResponse = $replayedAuthClient->post('/api/v1/logout', [
                'headers' => [
                    'Origin' => 'http://localhost:5173',
                    'Referer' => 'http://localhost:5173/',
                    'X-XSRF-TOKEN' => $authenticatedXsrfToken,
                ],
            ]);
            $this->assertSame(419, $staleCsrfResponse->getStatusCode(), 'Stale CSRF token must return 419');

            // 12. Fresh CSRF token supported: fresh token with bad credentials returns 401 (not 419)
            $freshJar = new CookieJar;
            $freshClient = $this->createClient($freshJar);
            $freshCsrfResponse = $freshClient->get('/sanctum/csrf-cookie', [
                'headers' => ['Origin' => 'http://localhost:5173'],
            ]);
            $this->assertSame(204, $freshCsrfResponse->getStatusCode());
            $freshXsrfCookie = $freshJar->getCookieByName('XSRF-TOKEN');
            $this->assertInstanceOf(SetCookie::class, $freshXsrfCookie);
            $freshXsrfToken = rawurldecode((string) $freshXsrfCookie->getValue());

            $freshPostResponse = $freshClient->post('/api/v1/login', [
                'headers' => [
                    'Origin' => 'http://localhost:5173',
                    'Referer' => 'http://localhost:5173/',
                    'X-XSRF-TOKEN' => $freshXsrfToken,
                ],
                'json' => [
                    'email' => self::SMOKE_EMAIL,
                    'password' => 'wrong-password',
                ],
            ]);
            $this->assertSame(401, $freshPostResponse->getStatusCode(), 'Fresh CSRF token is accepted and reaches credential validation');
        } finally {
            $server->stop(0);
        }
    }

    /**
     * Fails closed before any database query or process start.
     */
    private function assertIsolatedTestingRuntime(): void
    {
        $this->assertTrue(
            app()->environment('testing'),
            'Smoke tests require the testing environment.',
        );

        $this->assertSame(
            'pgsql',
            config('database.default'),
            'Default database connection must be pgsql.',
        );

        $database = config('database.connections.pgsql.database');
        $this->assertIsString($database);
        $this->assertSame(
            'starter_api_test',
            $database,
            'Smoke tests must target the dedicated starter_api_test database.',
        );

        $host = config('database.connections.pgsql.host');
        $this->assertIsString($host);
        $expectedHost = (string) (getenv('DB_HOST') ?: 'postgres-test');
        $this->assertSame(
            $expectedHost,
            $host,
            'Smoke tests must target the expected isolated postgres host.',
        );

        $this->assertNull(
            config('database.connections.pgsql.url'),
            'Refusing to run: pgsql config has a database URL configured.',
        );

        $rawDbUrl = getenv('DB_URL');
        $this->assertTrue(
            $rawDbUrl === false || $rawDbUrl === '',
            'Refusing to run: inherited DB_URL environment variable is present.',
        );

        $rawDatabaseUrl = getenv('DATABASE_URL');
        $this->assertTrue(
            $rawDatabaseUrl === false || $rawDatabaseUrl === '',
            'Refusing to run: inherited DATABASE_URL environment variable is present.',
        );

        $this->assertSame(
            '',
            (string) getenv('TEST_TOKEN'),
            'Refusing to run under parallel runner (TEST_TOKEN present).',
        );
    }

    /**
     * @param  array<string, string|false>  $environment
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
     * @return array<string, string|false>
     */
    private function serverEnvironment(): array
    {
        $dbHost = (string) (getenv('DB_HOST') ?: 'postgres-test');
        $dbPort = (string) (getenv('DB_PORT') ?: '5432');
        $dbPassword = (string) (getenv('TEST_DB_PASSWORD') ?: getenv('DB_PASSWORD'));

        return [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'APP_URL' => 'http://'.self::SERVER_HOST.':'.self::SERVER_PORT,
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => $dbHost,
            'DB_PORT' => $dbPort,
            'DB_DATABASE' => 'starter_api_test',
            'DB_USERNAME' => 'starter_api_test',
            'DB_PASSWORD' => $dbPassword,
            'DB_URL' => false,
            'DATABASE_URL' => false,
            'TEST_TOKEN' => false,
            'SESSION_DRIVER' => 'file',
            'SESSION_SECURE_COOKIE' => 'false',
            'CACHE_STORE' => 'file',
            'SANCTUM_STATEFUL_DOMAINS' => 'localhost,localhost:5173,127.0.0.1,127.0.0.1:8099',
            'LOG_CHANNEL' => 'stderr',
        ];
    }
}
