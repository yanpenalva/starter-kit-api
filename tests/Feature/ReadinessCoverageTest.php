<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\ReadinessChecks;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

/**
 * Focused coverage for wave 2A (FEAT-23-W2-A): App\Support\ReadinessChecks ran
 * in no test (HealthTest mocks it), so every executable line was uncovered.
 *
 * Assertions target observable behaviour — the returned state map and the
 * persisted failed_jobs rows — never that a line merely ran. Config changes are
 * scoped to the application rebuilt for every test, so none needs restoring.
 */
final class ReadinessCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function mockQueueExists(bool $exists): MockInterface
    {
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('where')->once()->withAnyArgs()->andReturnSelf();
        $builder->shouldReceive('exists')->once()->andReturn($exists);
        DB::shouldReceive('table')->once()->with('failed_jobs')->andReturn($builder);

        return $builder;
    }

    /**
     * A real worker driven by InfraQueueFailureTest commits failed_jobs rows
     * outside RefreshDatabase's transaction, so the table is not empty on entry.
     * Deleting inside this transaction keeps every queue assertion below scoped
     * to the row the test inserts, and rolls back with it.
     */
    private function purgeInheritedFailedJobs(): void
    {
        DB::table('failed_jobs')->delete();
    }

    public function test_run_reports_all_healthy_when_dependencies_ok(): void
    {
        DB::shouldReceive('selectOne')->once()->with('select 1')->andReturn((object) ['1' => 1]);
        Redis::shouldReceive('ping')->once()->andReturn('PONG');
        $this->mockQueueExists(false);

        $result = (new ReadinessChecks)->run();

        $this->assertSame(['database' => true, 'redis' => true, 'queue' => true], $result);
    }

    public function test_run_reports_database_failure_in_isolation(): void
    {
        DB::shouldReceive('selectOne')->once()->with('select 1')->andThrow(new RuntimeException('db down'));
        Redis::shouldReceive('ping')->once()->andReturn('PONG');
        $this->mockQueueExists(false);

        $result = (new ReadinessChecks)->run();

        $this->assertSame(['database' => false, 'redis' => true, 'queue' => true], $result);
    }

    public function test_run_reports_redis_true_boolean_as_healthy(): void
    {
        DB::shouldReceive('selectOne')->once()->with('select 1')->andReturn((object) ['1' => 1]);
        Redis::shouldReceive('ping')->once()->andReturn(true);
        $this->mockQueueExists(false);

        $result = (new ReadinessChecks)->run();

        $this->assertTrue($result['redis']);
        $this->assertSame(['database' => true, 'redis' => true, 'queue' => true], $result);
    }

    public function test_run_reports_redis_unexpected_payload_as_unhealthy(): void
    {
        DB::shouldReceive('selectOne')->once()->with('select 1')->andReturn((object) ['1' => 1]);
        Redis::shouldReceive('ping')->once()->andReturn('ERR');
        $this->mockQueueExists(false);

        $result = (new ReadinessChecks)->run();

        $this->assertSame(['database' => true, 'redis' => false, 'queue' => true], $result);
    }

    public function test_run_reports_redis_exception_as_unhealthy(): void
    {
        DB::shouldReceive('selectOne')->once()->with('select 1')->andReturn((object) ['1' => 1]);
        Redis::shouldReceive('ping')->once()->andThrow(new RuntimeException('redis down'));
        $this->mockQueueExists(false);

        $result = (new ReadinessChecks)->run();

        $this->assertSame(['database' => true, 'redis' => false, 'queue' => true], $result);
    }

    public function test_queue_reports_unhealthy_with_recent_failed_job(): void
    {
        Redis::shouldReceive('ping')->andReturn('PONG');
        config()->set('health.failed_jobs_window_minutes', 5);

        $this->purgeInheritedFailedJobs();

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'synthetic failure',
            'failed_at' => Carbon::now()->subMinute(),
        ]);

        $this->assertSame(1, (int) DB::table('failed_jobs')->count());

        $result = (new ReadinessChecks)->run();

        $this->assertTrue($result['database']);
        $this->assertTrue($result['redis']);
        $this->assertFalse($result['queue']);
    }

    public function test_queue_ignores_failed_job_outside_configured_window(): void
    {
        Redis::shouldReceive('ping')->andReturn('PONG');
        config()->set('health.failed_jobs_window_minutes', 5);

        $this->purgeInheritedFailedJobs();

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'old synthetic failure',
            'failed_at' => Carbon::now()->subMinutes(10),
        ]);

        $result = (new ReadinessChecks)->run();

        $this->assertSame(['database' => true, 'redis' => true, 'queue' => true], $result);
    }

    public function test_queue_falls_back_to_five_minutes_for_non_numeric_window(): void
    {
        Redis::shouldReceive('ping')->andReturn('PONG');
        config()->set('health.failed_jobs_window_minutes', 'not-a-number');

        $this->purgeInheritedFailedJobs();

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'synthetic failure inside fallback window',
            'failed_at' => Carbon::now()->subMinutes(4),
        ]);

        $result = (new ReadinessChecks)->run();

        $this->assertFalse($result['queue']);
    }

    public function test_queue_honours_numeric_string_window(): void
    {
        Redis::shouldReceive('ping')->andReturn('PONG');
        config()->set('health.failed_jobs_window_minutes', '1');

        $this->purgeInheritedFailedJobs();

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'synthetic failure outside one minute window',
            'failed_at' => Carbon::now()->subMinutes(2),
        ]);

        $result = (new ReadinessChecks)->run();

        $this->assertTrue($result['queue']);
        $this->assertSame(['database' => true, 'redis' => true, 'queue' => true], $result);
    }

    public function test_queue_reports_unhealthy_when_lookup_fails(): void
    {
        DB::shouldReceive('selectOne')->once()->with('select 1')->andReturn((object) ['1' => 1]);
        Redis::shouldReceive('ping')->once()->andReturn('PONG');
        DB::shouldReceive('table')->once()->with('failed_jobs')->andThrow(new RuntimeException('queue lookup down'));

        $result = (new ReadinessChecks)->run();

        $this->assertSame(['database' => true, 'redis' => true, 'queue' => false], $result);
    }
}
