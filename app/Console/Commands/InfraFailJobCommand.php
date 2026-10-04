<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\InfraFailingJob;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Dev-only smoke command: queues one synthetic always-failing job to
 * exercise retry/backoff and the failed_jobs record. Failure output is
 * synthetic by construction; no user data is involved.
 */
final class InfraFailJobCommand extends Command
{
    protected $signature = 'infra:fail-job';

    protected $description = 'Exclusivo de desenvolvimento: enfileira um job sintético de falha para exercitar retry/backoff e failed_jobs';

    public function handle(): int
    {
        // Env guard (F5): same contract as infra:probe — outside local/testing
        // this command must be a no-op SUCCESS: no job dispatched, no queue
        // write, no failed_jobs side effects.
        if (! in_array(app()->environment(), ['local', 'testing'], true)) {
            $this->info('Infra fail-job desativado fora de local/testing');

            return self::SUCCESS;
        }

        $token = (string) Str::uuid();
        InfraFailingJob::dispatch($token);

        // The token is the only correlation data printed; synthetic UUID.
        $this->info('Job sintético de falha enfileirado token='.$token);

        return self::SUCCESS;
    }
}
