<?php

declare(strict_types=1);

namespace Tests\Concern;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

/**
 * Shared helpers for the async infrastructure integration tests. All
 * runtime data is synthetic: `recipient@example.test`, UUID tokens,
 * generic message bodies. The bounded worker runs against the isolated
 * test stack only (redis-test / mailpit-test).
 */
trait RunsAsyncInfraStack
{
    /**
     * Run a bounded queue worker against the redis `testqueue`.
     * `--stop-when-empty` + `--max-jobs` keep it ephemeral.
     *
     * @param  list<string>  $extra
     */
    private function runBoundedWorker(int $tries, array $extra = []): Process
    {
        $arguments = [
            PHP_BINARY,
            base_path('artisan'),
            'queue:work',
            'redis',
            '--queue=testqueue',
            '--stop-when-empty',
            '--max-jobs=50',
            '--tries='.$tries,
            ...$extra,
        ];

        $process = new Process(
            $arguments,
            base_path(),
            $this->workerEnvironment(),
            null,
            120.0,
        );
        $process->run();

        return $process;
    }

    /**
     * Explicit environment for the spawned worker: identical to the test
     * container, but with the redis queue and SMTP mailer the worker needs.
     *
     * @return array<string, string>
     */
    private function workerEnvironment(): array
    {

        $redisHost = getenv('REDIS_HOST');
        $dbHost = getenv('ASYNC_WORKER_DB_HOST');
        $mailHost = getenv('ASYNC_WORKER_MAIL_HOST');
        $connection = config('database.default');
        $connectionConfig = config("database.connections.{$connection}");

        return [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_KEY' => $this->stringConfig('app.key', ''),
            'DB_CONNECTION' => $connection,
            'DB_HOST' => is_string($dbHost) && $dbHost !== '' ? $dbHost : (string) ($connectionConfig['host'] ?? ''),
            'DB_PORT' => (string) ($connectionConfig['port'] ?? '5432'),
            'DB_DATABASE' => $this->stringConfig("database.connections.{$connection}.database", 'starter_api_test'),
            'DB_USERNAME' => $this->stringConfig("database.connections.{$connection}.username", ''),
            'DB_PASSWORD' => $this->stringConfig("database.connections.{$connection}.password", ''),
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'redis',
            'REDIS_HOST' => is_string($redisHost) && $redisHost !== '' ? $redisHost : $this->stringConfig('database.redis.default.host', 'redis-test'),
            'REDIS_PORT' => '6379',
            'REDIS_QUEUE' => 'testqueue',
            'QUEUE_AFTER_COMMIT' => 'true',
            'MAIL_MAILER' => 'smtp',
            'MAIL_HOST' => is_string($mailHost) && $mailHost !== '' ? $mailHost : $this->stringConfig('mail.mailers.smtp.host', 'mailpit-test'),
            'MAIL_PORT' => '1025',
            'MAIL_FROM_ADDRESS' => 'no-reply@example.test',
            'MAIL_FROM_NAME' => 'Starter Kit API',
            'LOG_CHANNEL' => 'stderr',
            'LOG_LEVEL' => 'warning',
        ];
    }

    private function stringConfig(string $key, string $default): string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function mailpitApiUrl(): string
    {
        return rtrim($this->stringConfig('mail.infra_smoke.mailpit_api_url', 'http://mailpit-test:8025'), '/');
    }

    /**
     * @return array{total: int, subjects: list<string>, recipients: list<string>}
     */
    private function mailpitSnapshot(): array
    {
        $response = Http::timeout(5)->get($this->mailpitApiUrl().'/api/v1/messages');
        if ($response->failed()) {
            self::fail(sprintf(
                'Mailpit API unreachable at %s (status %d) — real infrastructure required, not skipping',
                $this->mailpitApiUrl(),
                $response->status(),
            ));
        }

        $payload = $response->json();
        $total = is_array($payload) && isset($payload['total']) && is_int($payload['total']) ? $payload['total'] : 0;
        $messages = is_array($payload) && isset($payload['messages']) && is_array($payload['messages']) ? $payload['messages'] : [];

        $subjects = [];
        $recipients = [];
        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }
            if (isset($message['Subject']) && is_string($message['Subject'])) {
                $subjects[] = $message['Subject'];
            }
            if (isset($message['To']) && is_array($message['To'])) {
                foreach ($message['To'] as $to) {
                    if (is_array($to) && isset($to['Address']) && is_string($to['Address'])) {
                        $recipients[] = $to['Address'];
                    }
                }
            }
        }

        return ['total' => $total, 'subjects' => $subjects, 'recipients' => $recipients];
    }

    /**
     * Poll Mailpit until a message with the token in the subject appears.
     *
     * @return array{total: int, subjects: list<string>, recipients: list<string>}
     */
    private function waitForMailpitMessage(string $token, int $attempts = 30): array
    {
        $snapshot = ['total' => 0, 'subjects' => [], 'recipients' => []];
        for ($i = 0; $i < $attempts; $i++) {
            $snapshot = $this->mailpitSnapshot();
            foreach ($snapshot['subjects'] as $subject) {
                if (str_contains($subject, $token)) {
                    return $snapshot;
                }
            }
            usleep(500_000);
        }

        self::fail(sprintf(
            'Synthetic message with token %s never reached Mailpit after %d attempts (last total: %d)',
            $token,
            $attempts,
            $snapshot['total'],
        ));
    }

    /**
     * Delete every message from the isolated Mailpit instance (cleanup).
     */
    private function flushMailpit(): void
    {
        Http::timeout(5)->delete($this->mailpitApiUrl().'/api/v1/messages');
    }
}
