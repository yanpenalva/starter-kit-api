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
    protected $signature = 'infra:probe {--key=infra:probe:last : Chave do marcador no Redis}';

    protected $description = 'Sonda Redis exclusiva de desenvolvimento que grava um marcador efêmero do scheduler (infra assíncrona)';

    public function handle(): int
    {

        if (! in_array(app()->environment(), ['local', 'testing'], true)) {
            $this->info('Sonda de infra desativada fora de local/testing');

            return self::SUCCESS;
        }

        $key = (string) $this->option('key');
        $connection = Redis::connection();
        $connection->ping();
        $connection->set($key, now()->toIso8601String());
        $connection->expire($key, 300);

        $this->info('Sonda Redis OK');

        return self::SUCCESS;
    }
}
