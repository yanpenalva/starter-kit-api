<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\BootstrapAdmin;
use Illuminate\Console\Command;

final class PromoteAdmin extends Command
{
    protected $signature = 'app:promote-admin
        {id : ID numérico da conta escolhida, verificada e ativa}
        {--confirm-production : Confirma explicitamente um bootstrap administrativo em produção}';

    protected $description = 'Concede de forma idempotente o papel de administrador à conta escolhida dentro de uma transação auditada';

    public function handle(BootstrapAdmin $bootstrap): int
    {
        $id = (int) $this->argument('id');

        if ($id < 1) {
            $this->error('O alvo deve ser um ID de conta numérico positivo.');

            return self::FAILURE;
        }

        try {
            $result = $bootstrap->run($id, (bool) $this->option('confirm-production'));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($result['already_admin']) {
            $this->info('A conta já possui o papel de administrador; nada foi alterado.');
        } else {
            $this->info('Papel de administrador concedido e auditado.');
        }

        return self::SUCCESS;
    }
}
