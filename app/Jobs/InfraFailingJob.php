<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

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
