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

    protected $description = 'Dev-only: queue one synthetic failing job to prove retry/backoff and failed_jobs';

    public function handle(): int
    {
        $token = (string) Str::uuid();
        InfraFailingJob::dispatch($token);

        // The token is the only correlation data printed; synthetic UUID.
        $this->info('queued synthetic failing job token='.$token);

        return self::SUCCESS;
    }
}
