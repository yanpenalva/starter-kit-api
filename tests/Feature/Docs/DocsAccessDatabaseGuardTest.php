<?php

declare(strict_types=1);

namespace Tests\Feature\Docs;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class DocsAccessDatabaseGuardTest extends TestCase
{
    public function test_allowed_postgres_hosts_pass_guard_without_database_access(): void
    {
        foreach (['postgres-test', '127.0.0.1', 'localhost'] as $host) {
            $configuration = $this->safeConfiguration();
            $configuration['host'] = $host;

            self::assertSame('accepted:1', $this->runFixture('clean', $configuration));
        }
    }

    public function test_unapproved_postgres_hosts_fail_before_database_access(): void
    {
        foreach ([null, '', 'pg.example.net', 'postgres://localhost/starter_api_test', ['127.0.0.1']] as $host) {
            $configuration = $this->safeConfiguration();
            $configuration['host'] = $host;

            self::assertSame(
                'rejected:0:Host Postgres de teste não permitido; migração cancelada.',
                $this->runFixture('clean', $configuration),
            );
        }
    }

    public function test_db_url_in_any_raw_environment_store_fails_before_database_access(): void
    {
        $scenarios = [
            'getenv-empty', 'getenv-zero', 'getenv-null-string',
            'env-null', 'env-empty', 'env-zero', 'env-null-string',
            'server-null', 'server-empty', 'server-zero', 'server-null-string',
        ];

        foreach ($scenarios as $scenario) {
            self::assertSame(
                'rejected:0:DB_URL herdada presente; migração de teste cancelada.',
                $this->runFixture($scenario, $this->safeConfiguration()),
                $scenario,
            );
        }
    }

    /**
     * @return array{
     *     connection: string,
     *     host: string,
     *     port: string,
     *     database: string,
     *     username: string,
     *     url: null,
     *     environment: string,
     *     debug: false,
     *     mailer: string,
     *     smtp_host: string,
     *     smtp_port: string
     * }
     */
    private function safeConfiguration(): array
    {
        return [
            'connection' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => '5432',
            'database' => 'starter_api_test',
            'username' => 'starter_api_test',
            'url' => null,
            'environment' => 'testing',
            'debug' => false,
            'mailer' => 'array',
            'smtp_host' => '127.0.0.1',
            'smtp_port' => '1025',
        ];
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function runFixture(string $scenario, array $configuration): string
    {
        $script = <<<'PHP'
        require 'vendor/autoload.php';
        require 'tests/Support/DocsAccessDatabaseGuard.php';
        $scenario = $argv[1];
        putenv('DB_URL');
        unset($_ENV['DB_URL'], $_SERVER['DB_URL']);
        match ($scenario) {
            'clean' => null,
            'getenv-empty' => putenv('DB_URL='),
            'getenv-zero' => putenv('DB_URL=0'),
            'getenv-null-string' => putenv('DB_URL=null'),
            'env-null' => $_ENV['DB_URL'] = null,
            'env-empty' => $_ENV['DB_URL'] = '',
            'env-zero' => $_ENV['DB_URL'] = '0',
            'env-null-string' => $_ENV['DB_URL'] = 'null',
            'server-null' => $_SERVER['DB_URL'] = null,
            'server-empty' => $_SERVER['DB_URL'] = '',
            'server-zero' => $_SERVER['DB_URL'] = '0',
            'server-null-string' => $_SERVER['DB_URL'] = 'null',
            default => throw new RuntimeException('Fixture inválida.'),
        };
        $configuration = json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR);
        $databaseCalls = 0;
        try {
            \Tests\Support\DocsAccessDatabaseGuard::assertSafe($configuration);
        } catch (RuntimeException $exception) {
            echo 'rejected:'.$databaseCalls.':'.$exception->getMessage();
            exit(0);
        }
        ++$databaseCalls;
        echo 'accepted:'.$databaseCalls;
        PHP;

        $process = new Process(
            [PHP_BINARY, '-r', $script, $scenario, json_encode($configuration, JSON_THROW_ON_ERROR)],
            dirname(__DIR__, 3),
            ['DB_URL' => false],
            timeout: 10,
        );
        $process->mustRun();

        return trim($process->getOutput());
    }
}
