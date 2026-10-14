<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Audit;
use App\Support\Correlation;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\Concern\UsesTypedDatabaseConfig;
use Tests\TestCase;

final class CorrelationRedactionTest extends TestCase
{
    use RefreshDatabase;
    use UsesTypedDatabaseConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = $this->databaseConnectionName();
        $database = $this->databaseConfigString($this->databaseConnectionConfig($connection), 'database');
        $this->assertSame($database, DB::connection()->getDatabaseName());

        if (config('database.default') === 'pgsql') {
            $this->assertSame('starter_api_test', $database);
        }

        $this->seed(RbacCatalogueSeeder::class);
    }

    public function test_valid_header_is_trusted_and_garbage_is_replaced(): void
    {
        $valid = 'b3a1d2c4-5f6e-4a7b-8c9d-0e1f2a3b4c5d';
        $this->assertSame($valid, Correlation::fromRequest($valid));

        $forged = Correlation::fromRequest('drop table users; cpffake');

        $this->assertNotSame('drop table users; cpffake', $forged);
        $this->assertTrue(Str::isUuid($forged));
    }

    public function test_middleware_echoes_a_uuid_correlation_header(): void
    {
        $response = $this->get('/api/v1');

        $response->assertOk();

        $correlation = (string) $response->headers->get('X-Correlation-Id');
        $this->assertTrue(Str::isUuid($correlation));
    }

    public function test_local_or_testing_preserves_a_valid_client_header_as_log_context(): void
    {
        $clientUuid = 'b3a1d2c4-5f6e-4a7b-8c9d-0e1f2a3b4c5d';

        $response = $this->get('/api/v1', ['X-Correlation-Id' => $clientUuid]);

        $response->assertOk();

        $this->assertSame($clientUuid, (string) $response->headers->get('X-Correlation-Id'));
        $this->assertSame($clientUuid, Correlation::current());
    }

    public function test_production_ignores_client_correlation_headers(): void
    {
        $foreign = 'c4d5e6f7-8a9b-4c0d-9e1f-2a3b4c5d6e7f';

        $this->app->instance('env', 'production');

        try {
            $first = $this->get('/api/v1', ['X-Correlation-Id' => $foreign]);
            $second = $this->get('/api/v1', ['X-Correlation-Id' => $foreign]);
        } finally {
            $this->app->instance('env', 'testing');
        }

        $first->assertOk();
        $second->assertOk();

        $firstCorrelation = (string) $first->headers->get('X-Correlation-Id');
        $secondCorrelation = (string) $second->headers->get('X-Correlation-Id');

        $this->assertNotSame($foreign, $firstCorrelation);
        $this->assertNotSame($foreign, $secondCorrelation);
        $this->assertTrue(Str::isUuid($firstCorrelation));
        $this->assertTrue(Str::isUuid($secondCorrelation));
        $this->assertNotSame($firstCorrelation, $secondCorrelation);
    }

    public function test_audit_envelope_never_contains_sensitive_values_even_when_offered(): void
    {
        Audit::record(
            'privileged.change',
            'user',
            1,
            Correlation::current(),
            [
                'password' => 'synthetic-secret',
                'cpf' => '00000000191',
                'email' => 'synthetic@example.test',
                'token' => 'synthetic-token',
                'full_payload' => '{"everything":"else"}',
                'actor_type' => 'cli',
                'action' => Audit::ACTION_USER_STATUS,
            ],
        );

        $activity = Activity::query()
            ->where('event', 'privileged.change')
            ->firstOrFail();

        $propertiesCollection = $activity->properties;
        $this->assertNotNull($propertiesCollection);

        $properties = $propertiesCollection->all();

        foreach (['password', 'cpf', 'email', 'token', 'full_payload'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $properties);
        }

        $encoded = $propertiesCollection->toJson();
        $this->assertStringNotContainsString('synthetic-secret', $encoded);
        $this->assertStringNotContainsString('00000000191', $encoded);
        $this->assertStringNotContainsString('synthetic@example.test', $encoded);
    }
}
