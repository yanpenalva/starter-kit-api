<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\InfraFailingJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Concern\RunsAsyncInfraStack;
use Tests\TestCase;

final class InfraQueueFailureTest extends TestCase
{
    use RefreshDatabase;
    use RunsAsyncInfraStack;

    public function test_failing_job_is_retried_with_backoff_then_stored_in_failed_jobs(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('The bounded worker targets the isolated PostgreSQL stack (RunsAsyncInfraStack), so the retry-to-failed_jobs proof only holds on PostgreSQL.');
        }

        // The worker commits its row to failed_jobs outside this transaction, so
        // an earlier run leaves rows behind: dropping them in transaction scope
        // keeps the 0 -> 1 counts below about this run's token only.
        DB::table('failed_jobs')->delete();

        config()->set('queue.default', 'redis');
        config()->set('queue.connections.redis.after_commit', false);

        $token = (string) Str::uuid();
        InfraFailingJob::dispatch($token);

        $worker = $this->runBoundedWorker(tries: 2, extra: ['--backoff=1', '--once']);
        $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());

        $this->assertSame(0, (int) DB::table('failed_jobs')->count());

        $delayed = (int) Redis::connection()->zcard('queues:testqueue:delayed');
        $this->assertSame(1, $delayed, 'job should sit in the delayed zset during backoff');

        usleep(1_200_000);

        $retryWorker = $this->runBoundedWorker(tries: 2, extra: ['--backoff=1']);
        $this->assertTrue($retryWorker->isSuccessful(), $retryWorker->getErrorOutput());

        $this->assertSame(1, (int) DB::table('failed_jobs')->count());

        $record = DB::table('failed_jobs')->first();
        $this->assertNotNull($record, 'exactly one failed_jobs record expected');
        $this->assertNotEmpty($record->uuid);
        $this->assertSame('testqueue', $record->queue);

        $exception = $record->exception;
        $this->assertIsString($exception);
        $this->assertStringContainsString('infra synthetic failure', $exception);

        $this->assertSame(0, (int) Redis::connection()->llen('queues:testqueue'));
        $this->assertSame(0, (int) Redis::connection()->zcard('queues:testqueue:delayed'));
    }

    public function test_failed_jobs_schema_is_present_and_structured(): void
    {
        $names = Schema::getColumnListing('failed_jobs');

        $this->assertContains('uuid', $names);
        $this->assertContains('connection', $names);
        $this->assertContains('queue', $names);
        $this->assertContains('payload', $names);
        $this->assertContains('exception', $names);
        $this->assertContains('failed_at', $names);
    }
}
