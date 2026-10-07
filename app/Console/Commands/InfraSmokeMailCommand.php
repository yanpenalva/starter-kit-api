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

    protected $description = 'Exclusivo de desenvolvimento: enfileira um e-mail sintético de teste pela pilha assíncrona';

    public function handle(): int
    {

        if (! in_array(app()->environment(), ['local', 'testing'], true)) {
            $this->info('Infra smoke-mail desativado fora de local/testing');

            return self::SUCCESS;
        }

        $token = (string) Str::uuid();
        Mail::queue(new SyntheticInfraMail($token));

        $this->info('E-mail sintético de teste enfileirado token='.$token);

        return self::SUCCESS;
    }
}
