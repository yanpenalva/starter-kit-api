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

/**
 * FEAT-20: retry/backoff and the observable failure path. The synthetic
 * job fails on attempt 1 (re-released with a 1s backoff delay) and is
 * stored in failed_jobs after the final attempt. Assertions use counts
 * and the synthetic exception text only — payload bodies are never
 * dumped.
 */
final class InfraQueueFailureTest extends TestCase
{
    use RefreshDatabase;
    use RunsAsyncInfraStack;

    public function test_failing_job_is_retried_with_backoff_then_stored_in_failed_jobs(): void
    {
        config()->set('queue.default', 'redis');

        $token = (string) Str::uuid();
        InfraFailingJob::dispatch($token);

        $worker = $this->runBoundedWorker(tries: 2, extra: ['--backoff=1', '--once']);
        $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());

        // First attempt exhausted: no failure record yet, job waiting out the backoff.
        $this->assertSame(0, (int) DB::table('failed_jobs')->count());

        $delayed = (int) Redis::connection()->zcard('queues:testqueue:delayed');
        $this->assertSame(1, $delayed, 'job should sit in the delayed zset during backoff');

        // Wait out the 1s backoff, then drain the retry with a bounded worker.
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

        // Queue fully drained: nothing left pending or delayed.
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
