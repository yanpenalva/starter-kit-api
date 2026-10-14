<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\InfraFailingJob;
use App\Models\User;
use App\Support\Audit;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Focused coverage for `app:promote-admin`, `infra:fail-job` and the synthetic
 * failing job: exit code, command output and persisted state.
 */
final class InfraCommandCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacCatalogueSeeder::class);
    }

    /**
     * Runs an artisan command with a temporary environment override, always restored.
     *
     * @param  array<string, mixed>  $parameters
     * @return array{exit: int, output: string}
     */
    private function runInEnvironment(string $environment, string $command, array $parameters = []): array
    {
        $previous = $this->app['env'];
        $this->app->instance('env', $environment);

        try {
            $exit = Artisan::call($command, $parameters);
            $output = Artisan::output();
        } finally {
            $this->app->instance('env', $previous);
        }

        return ['exit' => $exit, 'output' => $output];
    }

    public function test_promote_admin_rejects_non_positive_and_non_numeric_ids(): void
    {
        $target = User::factory()->create();
        $targetKey = $target->getKey();
        $this->assertIsInt($targetKey);

        foreach (['0', '-7', 'not-a-number'] as $id) {
            $exit = Artisan::call('app:promote-admin', ['id' => $id]);
            $output = Artisan::output();

            $this->assertSame(1, $exit, "id={$id}");
            $this->assertStringContainsString('O alvo deve ser um ID de conta numérico positivo.', $output, "id={$id}");
            $this->assertStringNotContainsString('administrador concedido', $output, "id={$id}");
        }

        $target->refresh();
        $this->assertFalse($target->hasRole('admin'));
        $this->assertDatabaseCount('model_has_roles', 0);
        $this->assertDatabaseCount('activity_log', 0);
    }

    public function test_promote_admin_reports_bootstrap_failures_and_keeps_the_account_unchanged(): void
    {
        $unverifiedKey = User::factory()->unverified()->create()->getKey();
        $deactivatedKey = User::factory()->deactivated()->create()->getKey();
        $this->assertIsInt($unverifiedKey);
        $this->assertIsInt($deactivatedKey);

        $cases = [
            '999999' => 'O alvo do bootstrap não existe.',
            (string) $unverifiedKey => 'O alvo do bootstrap não está verificado.',
            (string) $deactivatedKey => 'O alvo do bootstrap está desativado.',
        ];

        foreach ($cases as $id => $message) {
            $exit = Artisan::call('app:promote-admin', ['id' => $id]);
            $output = Artisan::output();

            $this->assertSame(1, $exit, "id={$id}");
            $this->assertStringContainsString($message, $output, "id={$id}");
        }

        $this->assertDatabaseCount('model_has_roles', 0);
        $this->assertDatabaseCount('activity_log', 0);
    }

    public function test_promote_admin_reports_idempotency_for_an_existing_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $exit = Artisan::call('app:promote-admin', ['id' => (string) $admin->id]);
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);
        $this->assertStringContainsString('A conta já possui o papel de administrador; nada foi alterado.', $output);
        $this->assertStringNotContainsString('administrador concedido', $output);

        $this->assertTrue($admin->refresh()->hasRole('admin'));
        $this->assertDatabaseCount('model_has_roles', 1);
        $this->assertDatabaseCount('activity_log', 0);
    }

    public function test_promote_admin_grants_the_role_and_records_the_audited_envelope(): void
    {
        $target = User::factory()->create();

        $exit = Artisan::call('app:promote-admin', ['id' => (string) $target->id]);
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);
        $this->assertStringContainsString('Papel de administrador concedido e auditado.', $output);

        $this->assertTrue($target->refresh()->hasRole('admin'));
        $this->assertDatabaseHas('model_has_roles', [
            'model_id' => $target->id,
            'model_type' => User::class,
        ]);

        $activity = Activity::query()
            ->where('event', Audit::EVENT_CLI_BOOTSTRAP)
            ->where('subject_id', $target->id)
            ->firstOrFail();

        $this->assertSame(Audit::ACTION_BOOTSTRAP, $activity->properties['action'] ?? null);
        $this->assertSame('cli', $activity->properties['actor_type'] ?? null);
    }

    public function test_promote_admin_requires_the_production_confirmation_flag(): void
    {
        $target = User::factory()->create();
        $this->app->instance('env', 'before-production-command');

        $refused = $this->runInEnvironment('production', 'app:promote-admin', ['id' => (string) $target->id]);

        $this->assertSame('before-production-command', $this->app['env']);
        $this->assertSame(1, $refused['exit'], $refused['output']);
        $this->assertStringContainsString('O bootstrap em produção exige a flag de confirmação explícita.', $refused['output']);
        $this->assertFalse($target->refresh()->hasRole('admin'));
        $this->assertDatabaseCount('model_has_roles', 0);

        $confirmed = $this->runInEnvironment('production', 'app:promote-admin', [
            'id' => (string) $target->id,
            '--confirm-production' => true,
        ]);

        $this->assertSame(0, $confirmed['exit'], $confirmed['output']);
        $this->assertStringContainsString('Papel de administrador concedido e auditado.', $confirmed['output']);
        $this->assertTrue($target->refresh()->hasRole('admin'));
    }

    public function test_infra_fail_job_dispatches_a_synthetic_job_in_local_and_testing(): void
    {
        foreach (['testing', 'local'] as $environment) {
            Queue::fake();

            $result = $this->runInEnvironment($environment, 'infra:fail-job');

            $this->assertSame(0, $result['exit'], $result['output']);
            $this->assertStringNotContainsString('desativado fora de local/testing', $result['output'], $environment);

            Queue::assertPushed(InfraFailingJob::class, 1);

            $pushed = null;
            Queue::assertPushed(
                InfraFailingJob::class,
                static function (InfraFailingJob $job) use (&$pushed): bool {
                    $pushed = $job;

                    return true;
                },
            );

            $this->assertInstanceOf(InfraFailingJob::class, $pushed, $environment);
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
                $pushed->token,
                $environment,
            );
            $this->assertStringContainsString(
                'Job sintético de falha enfileirado token='.$pushed->token,
                $result['output'],
                $environment,
            );
        }
    }

    public function test_infra_fail_job_is_inert_outside_local_and_testing(): void
    {
        foreach (['production', 'staging'] as $environment) {
            Queue::fake();

            $result = $this->runInEnvironment($environment, 'infra:fail-job');

            $this->assertSame(0, $result['exit'], $result['output']);
            $this->assertStringContainsString('Infra fail-job desativado fora de local/testing', $result['output'], $environment);
            Queue::assertNothingPushed();
        }
    }

    public function test_infra_failing_job_throws_its_synthetic_failure_under_the_retry_policy(): void
    {
        $job = new InfraFailingJob('synthetic-token-w1a');

        $this->assertSame('synthetic-token-w1a', $job->token);
        $this->assertSame(2, $job->tries);
        $this->assertSame([1], $job->backoff);
        $this->assertInstanceOf(ShouldQueue::class, $job);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('infra synthetic failure (token synthetic-token-w1a)');

        $job->handle();
    }
}
