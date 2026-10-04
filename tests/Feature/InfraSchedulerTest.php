<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * FEAT-20: the scheduler fixture. The dev-only `infra:probe` command must
 * be registered on the schedule and must write its short-lived Redis
 * marker when executed.
 */
final class InfraSchedulerTest extends TestCase
{
    public function test_infra_probe_is_registered_on_the_schedule(): void
    {
        Artisan::call('schedule:list');

        $this->assertStringContainsString('infra:probe', Artisan::output());
    }

    public function test_infra_probe_writes_its_redis_marker(): void
    {
        $exit = Artisan::call('infra:probe');

        $this->assertSame(0, $exit);

        $marker = Redis::connection()->get('infra:probe:last');
        $this->assertIsString($marker);
        $this->assertNotSame('', $marker);
    }

    public function test_schedule_object_carries_the_probe_entry(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $events = $schedule->events();

        $probe = null;
        foreach ($events as $event) {
            if (str_contains((string) $event->command, 'infra:probe')) {
                $probe = $event;
                break;
            }
        }

        $this->assertNotNull($probe, 'infra:probe fixture must be scheduled');
    }
}
