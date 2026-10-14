<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Providers\TelescopeServiceProvider;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\IncomingExceptionEntry;
use Laravel\Telescope\Telescope;
use Mockery;
use RuntimeException;
use Tests\Concern\UsesTypedDatabaseConfig;
use Tests\TestCase;

final class TelescopeServiceProviderTest extends TestCase
{
    use RefreshDatabase;
    use UsesTypedDatabaseConfig;

    public function test_only_admin_users_can_pass_the_telescope_gate(): void
    {
        $this->seed(RbacCatalogueSeeder::class);
        $user = User::factory()->create();

        $this->assertFalse(Gate::forUser(null)->allows('viewTelescope'));
        $this->assertFalse(Gate::forUser($user)->allows('viewTelescope'));

        $user->assignRole(Role::Admin->value);

        $this->assertTrue(Gate::forUser($user)->allows('viewTelescope'));
    }

    public function test_only_verified_active_admin_users_can_pass_the_pulse_gate(): void
    {
        $this->seed(RbacCatalogueSeeder::class);
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole(Role::Admin->value);
        $unverifiedAdmin = User::factory()->create(['email_verified_at' => null]);
        $unverifiedAdmin->assignRole(Role::Admin->value);
        $deactivatedAdmin = User::factory()->create([
            'email_verified_at' => now(),
            'deactivated_at' => now(),
        ]);
        $deactivatedAdmin->assignRole(Role::Admin->value);

        $this->assertFalse(Gate::forUser(null)->allows('viewPulse'));
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('viewPulse'));
        $this->assertFalse(Gate::forUser($unverifiedAdmin)->allows('viewPulse'));
        $this->assertFalse(Gate::forUser($deactivatedAdmin)->allows('viewPulse'));
        $this->assertTrue(Gate::forUser($admin)->allows('viewPulse'));
    }

    public function test_non_local_filter_keeps_only_reportable_failed_or_monitored_entries(): void
    {
        $filterIndex = array_key_last(Telescope::$filterUsing);
        self::assertNotNull($filterIndex);
        $filter = Telescope::$filterUsing[$filterIndex];
        self::assertIsCallable($filter);

        $ordinaryEntry = (new IncomingEntry([]))->type(EntryType::QUERY);
        $failedRequest = (new IncomingEntry(['response_status' => 500]))->type(EntryType::REQUEST);
        $failedJob = (new IncomingEntry(['status' => 'failed']))->type(EntryType::JOB);
        $exception = new class(new RuntimeException('reportable'), []) extends IncomingExceptionEntry
        {
            public function isReportableException(): bool
            {
                return true;
            }
        };

        $repository = Mockery::mock(EntriesRepository::class);
        $repository->shouldReceive('isMonitoring')->once()->andReturnTrue();
        $this->app->instance(EntriesRepository::class, $repository);
        $monitoredEntry = (new IncomingEntry([]))->type(EntryType::QUERY)->tags(['monitor-me']);

        $this->assertFalse($filter($ordinaryEntry));
        $this->assertTrue($filter($failedRequest));
        $this->assertTrue($filter($failedJob));
        $this->assertTrue($filter($exception));
        $this->assertTrue($filter($monitoredEntry));
    }

    public function test_local_filter_keeps_all_entries(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'local');
        (new TelescopeServiceProvider($this->app))->register();

        $filterIndex = array_key_last(Telescope::$filterUsing);
        self::assertNotNull($filterIndex);
        $filter = Telescope::$filterUsing[$filterIndex];
        self::assertIsCallable($filter);
        $this->assertTrue($filter((new IncomingEntry([]))->type(EntryType::QUERY)));
    }

    public function test_sensitive_request_keys_are_hidden_outside_local(): void
    {
        $this->assertContains('_token', Telescope::$hiddenRequestParameters);
        $this->assertContains('cookie', Telescope::$hiddenRequestHeaders);
        $this->assertContains('x-csrf-token', Telescope::$hiddenRequestHeaders);
        $this->assertContains('x-xsrf-token', Telescope::$hiddenRequestHeaders);
    }
}
