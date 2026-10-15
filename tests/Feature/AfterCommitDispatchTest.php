<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\SyntheticInfraMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * No RefreshDatabase: its outer transaction prevents afterCommit callbacks
 * from firing until the outermost transaction commits.
 */
final class AfterCommitDispatchTest extends TestCase
{
    private const string PROBE_QUEUE = 'queues:aftercommit-probe';

    protected function tearDown(): void
    {
        if ($this->redisIsReachable()) {
            Redis::connection()->del(
                self::PROBE_QUEUE,
                self::PROBE_QUEUE.':delayed',
                self::PROBE_QUEUE.':reserved',
                self::PROBE_QUEUE.':notify',
            );
        }
        parent::tearDown();
    }

    private function redisIsReachable(): bool
    {
        try {
            Redis::connection()->ping();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function useProbeQueue(): void
    {
        config()->set('queue.default', 'redis');
        config()->set('queue.connections.redis.queue', 'aftercommit-probe');
    }

    private function useIsolatedDatabaseQueueWithoutAfterCommit(): void
    {
        config()->set('database.connections.queue_probe', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('queue.default', 'database');
        config()->set('queue.connections.database.connection', 'queue_probe');
        config()->set('queue.connections.database.after_commit', false);

        $this->artisan('migrate', [
            '--database' => 'queue_probe',
            '--path' => 'database/migrations/2026_10_06_000001_create_jobs_table.php',
        ]);
    }

    private function probeJobsCount(): int
    {
        return DB::connection('queue_probe')->table('jobs')->count();
    }

    private function probeQueueSize(): int
    {
        return (int) Redis::connection()->llen(self::PROBE_QUEUE)
            + (int) Redis::connection()->zcard(self::PROBE_QUEUE.':delayed');
    }

    public function test_rolled_back_transaction_enqueues_nothing(): void
    {
        if (! $this->redisIsReachable()) {
            $this->markTestSkipped('Redis is not reachable');
        }

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
        if (! $this->redisIsReachable()) {
            $this->markTestSkipped('Redis is not reachable');
        }

        $this->useProbeQueue();
        config()->set('queue.connections.redis.after_commit', true);
        $this->assertSame(0, $this->probeQueueSize());

        DB::transaction(function (): void {
            Mail::queue(new SyntheticInfraMail('commit-should-enqueue'));
        });

        $this->assertSame(1, $this->probeQueueSize(), 'commit must leave exactly one queued job');
    }

    public function test_user_notifications_do_not_enqueue_on_rollback(): void
    {
        $this->useIsolatedDatabaseQueueWithoutAfterCommit();

        $user = User::factory()->make();
        $outcome = 'unexpected-commit';

        try {
            DB::transaction(function () use ($user): void {
                $user->sendEmailVerificationNotification();
                $user->sendPasswordResetNotification('probe-token');
                throw new RuntimeException('synthetic rollback');
            });
        } catch (RuntimeException) {
            $outcome = 'rolled-back';
        }

        $this->assertSame('rolled-back', $outcome);
        $this->assertSame(0, $this->probeJobsCount(), 'rollback must not enqueue notifications');
    }

    public function test_user_notifications_enqueue_on_commit(): void
    {
        $this->useIsolatedDatabaseQueueWithoutAfterCommit();

        $user = User::factory()->make();

        DB::transaction(function () use ($user): void {
            $user->sendEmailVerificationNotification();
            $user->sendPasswordResetNotification('probe-token');
        });

        $this->assertSame(2, $this->probeJobsCount(), 'commit must enqueue both notifications');
    }

    public function test_redis_connection_env_gates_after_commit(): void
    {
        if (! $this->redisIsReachable()) {
            $this->markTestSkipped('Redis is not reachable');
        }

        $raw = getenv('QUEUE_AFTER_COMMIT');
        $enabled = is_string($raw) && $raw !== '' && $raw !== '0' && filter_var($raw, FILTER_VALIDATE_BOOL);
        $this->assertSame($enabled, (bool) config('queue.connections.redis.after_commit'));
        $this->assertTrue($enabled);
    }
}
