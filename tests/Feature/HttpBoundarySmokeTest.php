<?php

declare(strict_types=1);

namespace Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Real HTTP smoke against a non-testing server instance (APP_ENV=local) with
 * its own cookie jar and the isolated test PostgreSQL database. The framework
 * APP_ENV=testing CSRF bypass is never used as proof here.
 */
final class HttpBoundarySmokeTest extends TestCase
{
    private const SERVER_HOST = '127.0.0.1';

    private const SERVER_PORT = '8097';

    private const SERVER_PORT_ALT = '8098';

    public function test_trusted_proxies_default_to_empty(): void
    {
        $request = Request::create('/up', 'GET');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->headers->set('X-Forwarded-For', '8.8.8.8');

        $this->app->make(Kernel::class)->handle($request);

        $this->assertSame('127.0.0.1', (string) $request->ip());
    }

    public function test_smoke_fixture_route_is_absent_without_the_flag(): void
    {
        $uris = array_map(
            static fn (\Illuminate\Routing\Route $route): string => $route->uri(),
            Route::getRoutes()->getRoutes(),
        );

        $this->assertNotContains('testing/csrf-echo', $uris);
    }

    public function test_production_environment_never_loads_the_smoke_fixture(): void
    {
        $fixtureFlag = base_path('storage/framework/testing/http-smoke-fixture.enabled');
        @mkdir(directory: dirname($fixtureFlag), permissions: 0775, recursive: true);
        touch($fixtureFlag);

        // Simulated production configuration only: isolated test PG namespace,
        // ephemeral server, guarded names; no real production deployment.
        $environment = array_merge($this->serverEnvironment(), ['APP_ENV' => 'production']);

        $server = $this->startServer(self::SERVER_PORT_ALT, $environment);

        try {
            // Simulated production config: connect to the ephemeral local
            // server while presenting an allowed production Host (TrustHosts
            // rejects 127.0.0.1 outside local environments).
            $client = new Client([
                'base_uri' => 'http://'.self::SERVER_HOST.':'.self::SERVER_PORT_ALT,
                'cookies' => new CookieJar,
                'http_errors' => false,
                'timeout' => 5,
                // Port-less Host header: Symfony trusted-host validation is
                // port-sensitive, and localhost is the configured app URL host.
                'headers' => ['Host' => 'localhost'],
            ]);
            $last = '';

            $deadline = microtime(true) + 15;
            $status = 0;

            while (microtime(true) < $deadline) {
                try {
                    $response = $client->get('/up');
                    $status = $response->getStatusCode();

                    if ($status === 200) {
                        $last = '';
                        break;
                    }

                    $last = $status.' '.substr($response->getBody()->__toString(), 0, 120);
                } catch (\Throwable $exception) {
                    $last = get_class($exception).': '.$exception->getMessage();
                }

                usleep(200_000);
            }

            $this->assertSame(200, $status, 'Production /up never returned 200. Last: ['.$last.']');
            $this->assertSame(404, $client->post('/testing/csrf-echo')->getStatusCode());
        } finally {
            $server->stop(0);
            @unlink($fixtureFlag);
        }
    }

    public function test_real_server_enforces_csrf_cookies_origins_and_throttle(): void
    {
        $database = config('database.connections.pgsql.database');
        $this->assertIsString($database);
        $this->assertSame('starter_api_test', $database);

        $environment = $this->serverEnvironment();
        $this->runMigrations($environment);

        $fixtureFlag = base_path('storage/framework/testing/http-smoke-fixture.enabled');
        @mkdir(directory: dirname($fixtureFlag), permissions: 0775, recursive: true);
        touch($fixtureFlag);

        // Direct single-process `php -S` (no artisan parent): stop() kills the
        // server itself, so restarts are orphan-free and the array-cache
        // throttle window restarts deterministically at zero.
        $server = $this->startServer(self::SERVER_PORT, $environment);

        try {
            $client = $this->waitUntilServerIsUp($this->localClient(), $server);

            $this->assertCsrfCookieAndRealTokenFlow($client);
            $this->assertCorsAllowlistIsExact($client);

            $server->stop(0);
            $this->clearThrottleCache();
            $server = $this->startServer(self::SERVER_PORT, $environment);
            $this->waitUntilServerIsUp($this->localClient(), $server);
            $this->assertGenericThrottleApplies($server);
        } finally {
            $server->stop(0);
            @unlink($fixtureFlag);
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
            // The framework router requires index.php relative to the CWD:
            // the public/ docroot is the correct front-controller root.
            cwd: base_path('public'),
            env: $environment,
        );
        $server->setTimeout(120);
        $server->start();

        return $server;
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
            // Persistent file store: the throttle window survives the
            // classic per-request application boot (array store would reset
            // every request and never reach the limit).
            'CACHE_STORE' => 'file',
        ];
    }

    /**
     * @param  array<string, string>  $environment
     */
    private function runMigrations(array $environment): void
    {
        $migrate = new Process(
            [PHP_BINARY, 'artisan', 'migrate:fresh', '--force'],
            cwd: base_path(),
            env: $environment,
        );
        $migrate->setTimeout(120);
        $migrate->run();

        $this->assertSame(0, $migrate->getExitCode(), $migrate->getErrorOutput().$migrate->getOutput());
    }

    private function localClient(): Client
    {
        $jar = new CookieJar;

        return new Client([
            'base_uri' => 'http://'.self::SERVER_HOST.':'.self::SERVER_PORT,
            'cookies' => $jar,
            'http_errors' => false,
            'timeout' => 5,
        ]);
    }

    private function waitUntilServerIsUp(Client $client, ?Process $server = null): Client
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
            .($server === null ? '' : $server->getOutput().$server->getErrorOutput()).']');
    }

    private function assertCsrfCookieAndRealTokenFlow(Client $client): void
    {
        $cookie = $client->get('/sanctum/csrf-cookie');

        $this->assertSame(204, $cookie->getStatusCode());
        $jar = $client->getConfig('cookies');
        $xsrf = $jar instanceof CookieJar ? $jar->getCookieByName('XSRF-TOKEN') : null;
        $this->assertNotNull($xsrf, 'The encrypted XSRF-TOKEN cookie must be set.');

        // A browser exposes document.cookie percent-decoded; mirror that.
        $xsrfValue = rawurldecode((string) $xsrf->getValue());

        $missing = $client->post('/testing/csrf-echo');
        $this->assertSame(419, $missing->getStatusCode());

        $spoofed = $client->post('/testing/csrf-echo', [
            'headers' => [
                'Origin' => 'http://evil.example',
                'X-XSRF-TOKEN' => 'invalid-token',
            ],
        ]);
        $this->assertSame(419, $spoofed->getStatusCode());

        $tokenMatch = $client->post('/testing/csrf-echo', [
            'headers' => [
                'Origin' => 'http://evil.example',
                'X-XSRF-TOKEN' => $xsrfValue,
            ],
        ]);
        $this->assertSame(204, $tokenMatch->getStatusCode());

        $statefulOrigin = $client->post('/testing/csrf-echo', [
            'headers' => [
                'Origin' => 'http://localhost:5173',
                'X-XSRF-TOKEN' => $xsrfValue,
            ],
        ]);
        $this->assertSame(204, $statefulOrigin->getStatusCode());
    }

    private function assertCorsAllowlistIsExact(Client $client): void
    {
        $allowed = $client->get('/api/v1', ['headers' => ['Origin' => 'http://localhost:5173']]);
        $this->assertSame('http://localhost:5173', $allowed->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('true', $allowed->getHeaderLine('Access-Control-Allow-Credentials'));

        // Fruitcake echoes the single allowed origin even for foreign
        // requests; browsers only honor ACAO when it matches the request
        // origin, so a foreign origin can never read the response.
        $foreign = $client->get('/api/v1', ['headers' => ['Origin' => 'http://evil.example']]);
        $this->assertNotSame('http://evil.example', $foreign->getHeaderLine('Access-Control-Allow-Origin'));
    }

    /**
     * Removes only the framework cache data written by the isolated smoke
     * servers. The guarded path must point into the framework cache directory.
     */
    private function clearThrottleCache(): void
    {
        $cacheData = storage_path('framework/cache/data');

        $this->assertStringContainsString(
            'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'data',
            $cacheData,
        );

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($cacheData, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if (! $item instanceof \SplFileInfo) {
                continue;
            }

            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
    }

    private function assertGenericThrottleApplies(?Process $server = null): void
    {
        $plain = new Client([
            'base_uri' => 'http://'.self::SERVER_HOST.':'.self::SERVER_PORT,
            'http_errors' => false,
            'timeout' => 5,
        ]);

        $statuses = [];
        $firstBlock = null;

        for ($attempt = 0; $attempt < 62; $attempt++) {
            $response = $plain->get('/api/v1');
            $statuses[] = $response->getStatusCode();

            if ($response->getStatusCode() !== 200 && $firstBlock === null) {
                $firstBlock = substr($response->getBody()->__toString(), 0, 160);
            }
        }

        $this->assertContains(
            200,
            $statuses,
            'Expected a 200 among the first requests. First blocked body: ['.$firstBlock.'] Observed: '.implode(',', $statuses),
        );
        $this->assertContains(
            429,
            $statuses,
            'Expected the generic 60/min throttle. Observed: '.implode(',', $statuses)
            .' Server output: ['.($server === null ? '' : $server->getOutput().$server->getErrorOutput()).']',
        );
    }
}
