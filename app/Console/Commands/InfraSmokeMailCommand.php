<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\SyntheticInfraMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Dev-only smoke command: queues one synthetic mailable through the real
 * queue connection. It never touches real recipients — the envelope is
 * pinned to the reserved `.test` address from config/mail.php.
 */
final class InfraSmokeMailCommand extends Command
{
    protected $signature = 'infra:smoke-mail';

    protected $description = 'Dev-only: queue one synthetic test mail through the async stack';

    public function handle(): int
    {
        // Env guard (F5): same contract as infra:probe — outside local/testing
        // this command must be a no-op SUCCESS: no mailable queued, no send,
        // no queue write of any kind.
        if (! in_array(app()->environment(), ['local', 'testing'], true)) {
            $this->info('infra smoke-mail disabled outside local/testing');

            return self::SUCCESS;
        }

        $token = (string) Str::uuid();
        Mail::queue(new SyntheticInfraMail($token));

        // The token is the only correlation data printed; synthetic UUID.
        $this->info('queued synthetic mail token='.$token);

        return self::SUCCESS;
    }
}
