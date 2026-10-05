<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

final class DocsAccessDatabaseGuard
{
    private const DATABASE_HOSTS = ['postgres-test', '127.0.0.1', 'localhost'];

    private const MAILERS = ['array', 'log', 'smtp'];

    private const SMTP_HOSTS = ['127.0.0.1', 'localhost', 'mailpit-test'];

    private const REQUIRED_CONFIGURATION = [
        'connection', 'host', 'port', 'database', 'username', 'url',
        'environment', 'debug', 'mailer', 'smtp_host', 'smtp_port',
    ];

    /**
     * @param array{
     *     connection: mixed,
     *     host: mixed,
     *     port: mixed,
     *     database: mixed,
     *     username: mixed,
     *     url: mixed,
     *     environment: mixed,
     *     debug: mixed,
     *     mailer: mixed,
     *     smtp_host: mixed,
     *     smtp_port: mixed
     * } $configuration
     */
    public static function assertSafe(array $configuration): void
    {
        if (self::rawDbUrlPresent()) {
            throw new RuntimeException('DB_URL herdada presente; migração de teste cancelada.');
        }

        if (array_diff(self::REQUIRED_CONFIGURATION, array_keys($configuration)) !== []) {
            throw new RuntimeException('Configuração de teste incompleta; migração cancelada.');
        }

        if (! in_array($configuration['host'], self::DATABASE_HOSTS, true)) {
            throw new RuntimeException('Host Postgres de teste não permitido; migração cancelada.');
        }

        $safeDatabase = $configuration['connection'] === 'pgsql'
            && in_array($configuration['port'], [5432, '5432'], true)
            && $configuration['database'] === 'starter_api_test'
            && $configuration['username'] === 'starter_api_test'
            && $configuration['url'] === null
            && $configuration['environment'] === 'testing'
            && $configuration['debug'] === false;

        if (! $safeDatabase) {
            throw new RuntimeException('Configuração Postgres fora do alvo efêmero; migração cancelada.');
        }

        $safeMailer = in_array($configuration['mailer'], self::MAILERS, true)
            && in_array($configuration['smtp_host'], self::SMTP_HOSTS, true)
            && in_array($configuration['smtp_port'], [1025, '1025'], true);

        if (! $safeMailer) {
            throw new RuntimeException('Configuração de email de teste não isolada; execução cancelada.');
        }
    }

    public static function rawDbUrlPresent(): bool
    {
        return getenv('DB_URL') !== false
            || array_key_exists('DB_URL', $_ENV)
            || array_key_exists('DB_URL', $_SERVER);
    }
}
