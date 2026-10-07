<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\ReadinessChecks;
use Mockery;
use Tests\TestCase;

final class HealthTest extends TestCase
{
    public function test_health_reports_all_checks_healthy_without_authentication(): void
    {
        $mock = Mockery::mock(ReadinessChecks::class);
        $mock->shouldReceive('run')->once()->andReturn(['database' => true, 'redis' => true, 'queue' => true]);
        $this->app->instance(ReadinessChecks::class, $mock);

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'checks' => ['database' => true, 'redis' => true, 'queue' => true],
            ]);
    }

    public function test_health_returns_service_unavailable_for_each_isolated_failed_check(): void
    {
        foreach (['database', 'redis', 'queue'] as $failedCheck) {
            $checks = ['database' => true, 'redis' => true, 'queue' => true];
            $checks[$failedCheck] = false;

            $mock = Mockery::mock(ReadinessChecks::class);
            $mock->shouldReceive('run')->once()->andReturn($checks);
            $this->app->instance(ReadinessChecks::class, $mock);

            $this->getJson('/api/v1/health')
                ->assertStatus(503)
                ->assertExactJson(['status' => 'error', 'checks' => $checks]);
        }
    }
}
