<?php

declare(strict_types=1);

namespace Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class HttpBoundarySmokeTest extends TestCase
{
    private ?string $sqliteDatabasePath = null;

    private const SERVER_HOST = '127.0.0.1';

    private const SERVER_PORT = '8097';

    private const SERVER_PORT_ALT = '8098';

    protected function tearDown(): void
    {
        if (is_string($this->sqliteDatabasePath)) {
            @unlink($this->sqliteDatabasePath);
            @unlink($this->sqliteDatabasePath.'-journal');
        }

        parent::tearDown();
    }

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

        $environment = array_merge($this->serverEnvironment(), [
            'APP_ENV' => 'production',
            'MAIL_MAILER' => 'array',
            'SESSION_SECURE_COOKIE' => 'true',
        ]);

        $server = $this->startServer(self::SERVER_PORT_ALT, $environment);

        try {

            $client = new Client([
                'base_uri' => 'http://'.self::SERVER_HOST.':'.self::SERVER_PORT_ALT,
                'cookies' => new CookieJar,
                'http_errors' => false,
                'timeout' => 5,

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
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");
        $this->assertIsString($database);
        $this->assertSame($database, DB::connection()->getDatabaseName());

        if (config('database.default') === 'pgsql') {
            $this->assertSame('starter_api_test', $database);
        }

        $environment = $this->serverEnvironment();
        $this->runMigrations($environment);

        $fixtureFlag = base_path('storage/framework/testing/http-smoke-fixture.enabled');
        @mkdir(directory: dirname($fixtureFlag), permissions: 0775, recursive: true);
        touch($fixtureFlag);

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
        $connection = config('database.default');
        $connectionConfig = config("database.connections.{$connection}");
        $database = (string) $connectionConfig['database'];

        if ($connection === 'sqlite') {
            if ($this->sqliteDatabasePath === null) {
                $databasePath = tempnam(sys_get_temp_dir(), 'starter-api-http-boundary-');
                $this->assertIsString($databasePath);
                $this->sqliteDatabasePath = $databasePath;
            }

            $database = (string) $this->sqliteDatabasePath;
        }

        return [
            'APP_ENV' => 'local',
            'APP_DEBUG' => 'false',
            'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'APP_URL' => 'http://'.self::SERVER_HOST.':'.self::SERVER_PORT,
            'DB_CONNECTION' => $connection,
            'DB_HOST' => (string) ($connectionConfig['host'] ?? $inherited('DB_HOST')),
            'DB_PORT' => (string) ($connectionConfig['port'] ?? $inherited('DB_PORT')),
            'DB_DATABASE' => $database,
            'DB_USERNAME' => (string) ($connectionConfig['username'] ?? ''),
            'DB_PASSWORD' => (string) ($connectionConfig['password'] ?? ''),
            'SESSION_DRIVER' => 'file',
            'SESSION_SECURE_COOKIE' => 'false',

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
