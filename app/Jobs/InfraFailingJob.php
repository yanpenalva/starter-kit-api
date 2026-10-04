<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Dev-only synthetic job that always fails, used to prove retry/backoff
 * and the failed_jobs record path. The exception message is synthetic
 * only — no user data, no secrets.
 */
final class InfraFailingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [1];

    public function __construct(public readonly string $token) {}

    public function handle(): void
    {
        throw new RuntimeException(sprintf(
            'infra synthetic failure (token %s)',
            $this->token,
        ));
    }
}
