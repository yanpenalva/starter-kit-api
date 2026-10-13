<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\InfraFailingJob;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class InfraFailJobCommand extends Command
{
    protected $signature = 'infra:fail-job';

    protected $description = 'Exclusivo de desenvolvimento: enfileira um job sintético de falha para exercitar retry/backoff e failed_jobs';

    public function handle(): int
    {

        if (! in_array(app()->environment(), ['local', 'testing'], true)) {
            $this->info('Infra fail-job desativado fora de local/testing');

            return self::SUCCESS;
        }

        $token = (string) Str::uuid();
        InfraFailingJob::dispatch($token);

        $this->info('Job sintético de falha enfileirado token='.$token);

        return self::SUCCESS;
    }
}
