<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\SyntheticInfraMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

/**
 * FEAT-20: afterCommit semantics proven against the REAL redis queue
 * (no fakes). The probe uses a dedicated queue name so leftovers from
 * other tests cannot interfere; queue keys are cleaned up afterwards.
 * NOTE: no RefreshDatabase here on purpose — RefreshDatabase wraps the
 * whole test in an outer transaction, and afterCommit callbacks only
 * fire when the OUTERMOST transaction commits.
 */
final class AfterCommitDispatchTest extends TestCase
{
    private const string PROBE_QUEUE = 'queues:aftercommit-probe';

    protected function tearDown(): void
    {
        Redis::connection()->del(
            self::PROBE_QUEUE,
            self::PROBE_QUEUE.':delayed',
            self::PROBE_QUEUE.':reserved',
            self::PROBE_QUEUE.':notify',
        );
        parent::tearDown();
    }

    private function useProbeQueue(): void
    {
        config()->set('queue.default', 'redis');
        config()->set('queue.connections.redis.queue', 'aftercommit-probe');
    }

    private function probeQueueSize(): int
    {
        return (int) Redis::connection()->llen(self::PROBE_QUEUE)
            + (int) Redis::connection()->zcard(self::PROBE_QUEUE.':delayed');
    }

    public function test_rolled_back_transaction_enqueues_nothing(): void
    {
        $this->useProbeQueue();
        config()->set('queue.connections.redis.after_commit', true);

        $outcome = 'unexpected-commit';

        try {
            DB::transaction(function (): void {
                Mail::queue(new SyntheticInfraMail('rollback-should-not-enqueue'));
                throw new RuntimeException('synthetic rollback');
            });
        } catch (RuntimeException) {
            $outcome = 'rolled-back';
        }

        $this->assertSame('rolled-back', $outcome);
        $this->assertSame(0, $this->probeQueueSize(), 'rollback must not enqueue any job');
    }

    public function test_committed_transaction_enqueues_the_job(): void
    {
        $this->useProbeQueue();
        config()->set('queue.connections.redis.after_commit', true);
        $this->assertSame(0, $this->probeQueueSize());

        DB::transaction(function (): void {
            Mail::queue(new SyntheticInfraMail('commit-should-enqueue'));
        });

        $this->assertSame(1, $this->probeQueueSize(), 'commit must leave exactly one queued job');
    }

    public function test_redis_connection_env_gates_after_commit(): void
    {

        $raw = getenv('QUEUE_AFTER_COMMIT');
        $enabled = is_string($raw) && $raw !== '' && $raw !== '0' && filter_var($raw, FILTER_VALIDATE_BOOL);
        $this->assertSame($enabled, (bool) config('queue.connections.redis.after_commit'));
        $this->assertTrue($enabled);
    }
}
