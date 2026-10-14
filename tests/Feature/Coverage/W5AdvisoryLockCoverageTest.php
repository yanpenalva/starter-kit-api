<?php

declare(strict_types=1);

namespace Tests\Feature\Coverage;

use App\Support\PrivilegeTransaction;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * PrivilegeTransaction::run() checa o advisory lock só no driver pgsql, e o
 * `select pg_advisory_xact_lock(...)` sempre devolve uma linha — a guarda de
 * falha não é alcançável por execução real em nenhum driver. O teste injeta a
 * resposta do driver que caracteriza o lock não adquirido (selectOne nulo).
 */
final class W5AdvisoryLockCoverageTest extends TestCase
{
    public function test_privilege_transaction_fails_when_the_advisory_lock_is_not_acquired(): void
    {
        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('getDriverName')->once()->andReturn('pgsql');

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(static fn (Closure $mutation): mixed => $mutation());
        DB::shouldReceive('connection')->once()->andReturn($connection);
        DB::shouldReceive('selectOne')->once()->andReturnNull();

        $mutationRan = false;

        try {
            PrivilegeTransaction::run(static function () use (&$mutationRan): string {
                $mutationRan = true;

                return 'mutation';
            });

            $this->fail('The transaction must fail when the advisory lock is not acquired.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'The privilege advisory lock could not be acquired.',
                $exception->getMessage(),
            );
        }

        $this->assertFalse($mutationRan, 'The mutation must not run without the advisory lock.');
    }
}
