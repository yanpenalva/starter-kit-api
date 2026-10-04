<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\BootstrapAdmin;
use Illuminate\Console\Command;

final class PromoteAdmin extends Command
{
    protected $signature = 'app:promote-admin
        {id : Numeric ID of the chosen, verified and active account}
        {--confirm-production : Explicitly confirm an administrative bootstrap against production}';

    protected $description = 'Idempotently grant the admin role to the chosen account inside one audited transaction';

    public function handle(BootstrapAdmin $bootstrap): int
    {
        $id = (int) $this->argument('id');

        if ($id < 1) {
            $this->error('The target must be a positive numeric account ID.');

            return self::FAILURE;
        }

        try {
            $result = $bootstrap->run($id, (bool) $this->option('confirm-production'));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($result['already_admin']) {
            $this->info('Account already holds the admin role; nothing changed.');
        } else {
            $this->info('Admin role granted and audited.');
        }

        return self::SUCCESS;
    }
}
