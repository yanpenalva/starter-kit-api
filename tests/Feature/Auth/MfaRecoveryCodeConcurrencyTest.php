<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\MfaRecoveryCodeService;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

final class MfaRecoveryCodeConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_concurrent_challenges_can_consume_a_recovery_code_once(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Concurrent row locking is verified on PostgreSQL in CI.');
        }

        if (! function_exists('pcntl_fork') || ! function_exists('stream_socket_pair')) {
            $this->markTestSkipped('Concurrent challenge workers require pcntl and local sockets.');
        }

        $code = 'RACE-CODE';
        $user = User::factory()->create();
        $user->forceFill([
            'mfa_secret' => app(TotpService::class)->generateSecret(),
            'mfa_recovery_codes' => app(MfaRecoveryCodeService::class)->hash([$code]),
            'mfa_confirmed_at' => now(),
        ])->save();

        $workers = [];

        for ($index = 0; $index < 2; $index++) {
            $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
            $this->assertIsArray($sockets);
            [$parentSocket, $childSocket] = $sockets;

            $processId = pcntl_fork();
            $this->assertNotSame(-1, $processId);

            if ($processId === 0) {
                fclose($parentSocket);
                DB::purge();
                fwrite($childSocket, 'R');

                if (fread($childSocket, 1) !== 'G') {
                    fclose($childSocket);
                    exit(1);
                }

                try {
                    $consumed = app(MfaRecoveryCodeService::class)->consume((int) $user->id, $code);
                    fwrite($childSocket, $consumed ? '1' : '0');
                    fclose($childSocket);
                    exit(0);
                } catch (Throwable) {
                    fwrite($childSocket, 'E');
                    fclose($childSocket);
                    exit(1);
                }
            }

            fclose($childSocket);
            $workers[] = ['pid' => $processId, 'socket' => $parentSocket];
        }

        foreach ($workers as $worker) {
            stream_set_timeout($worker['socket'], 10);
            $this->assertSame('R', fread($worker['socket'], 1));
        }

        foreach ($workers as $worker) {
            fwrite($worker['socket'], 'G');
        }

        $results = [];

        foreach ($workers as $worker) {
            stream_set_timeout($worker['socket'], 10);
            $results[] = fread($worker['socket'], 1);
            fclose($worker['socket']);
            $status = 0;
            pcntl_waitpid($worker['pid'], $status);
            $this->assertSame(0, $status);
        }

        sort($results);

        $this->assertSame(['0', '1'], $results);
        $freshUser = $user->fresh();
        $this->assertNotNull($freshUser);
        $this->assertSame([], $freshUser->mfa_recovery_codes);
    }
}
