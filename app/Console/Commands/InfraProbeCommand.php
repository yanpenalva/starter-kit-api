<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

/**
 * Dev-only async infrastructure probe: verifies the Redis connection and
 * stores a short-lived marker key. Used by the scheduler fixture and the
 * local smoke script; never scheduled in production.
 */
final class InfraProbeCommand extends Command
{
    protected $signature = 'infra:probe {--key=infra:probe:last : Marker key in Redis}';

    protected $description = 'Dev-only Redis probe that stores a short-lived scheduler marker (async infra)';

    public function handle(): int
    {
        // Env guard (F1): outside local/testing this command must be a
        // no-op SUCCESS — no Redis connection, no keys written.
        if (! in_array(app()->environment(), ['local', 'testing'], true)) {
            $this->info('infra probe disabled outside local/testing');

            return self::SUCCESS;
        }

        $key = (string) $this->option('key');
        $connection = Redis::connection();
        $connection->ping();
        $connection->set($key, now()->toIso8601String());
        $connection->expire($key, 300);

        $this->info('redis probe ok');

        return self::SUCCESS;
    }
}
