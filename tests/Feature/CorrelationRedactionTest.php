<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Audit;
use App\Support\Correlation;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

final class CorrelationRedactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $database = config('database.connections.pgsql.database');
        $this->assertIsString($database);
        $this->assertSame('starter_api_test', $database);

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

    /**
     * R1 regression: outside production, a valid client UUID is preserved in
     * the response header and matches the structured log context.
     */
    public function test_local_or_testing_preserves_a_valid_client_header_as_log_context(): void
    {
        $clientUuid = 'b3a1d2c4-5f6e-4a7b-8c9d-0e1f2a3b4c5d';

        $response = $this->get('/api/v1', ['X-Correlation-Id' => $clientUuid]);

        $response->assertOk();

        $this->assertSame($clientUuid, (string) $response->headers->get('X-Correlation-Id'));
        $this->assertSame($clientUuid, Correlation::current());
    }

    /**
     * R1 regression: production ignores the incoming UUID (body-guard for the
     * contract in Correlation::fromRequest) and answers with a fresh valid
     * UUID, unique across consecutive requests. Simulated environment only —
     * isolated test PG, no real deployment target.
     */
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

    public function test_concurrent_admin_mutations_serialize_through_the_global_lock(): void
    {
        config(['database.connections.conc' => config('database.connections.pgsql')]);

        $first = User::factory()->create();
        $first->assignRole('admin');
        $second = User::factory()->create();
        $second->assignRole('admin');

        // Connection A already changed an admin inside the open test
        // transaction; the global advisory lock must make connection B wait
        // on it (short lock timeout proves the serialization point), so a
        // second writer can never race past the last-admin accounting.
        $this->assertNotNull(
            DB::table('model_has_roles')->where('model_id', $second->getKey())->first(),
        );

        // Writer A holds the global privilege lock inside its transaction.
        DB::selectOne('select pg_advisory_xact_lock(?) as acquired', [727423]);

        DB::table('model_has_roles')->where('model_id', $second->getKey())->delete();

        $concurrent = DB::connection('conc');
        $concurrent->statement("SET lock_timeout TO '500ms'");

        try {
            $concurrent->selectOne('select pg_advisory_xact_lock(?) as acquired', [727423]);
            $this->fail('The second writer must block on the global privilege lock.');
        } catch (QueryException $exception) {
            $this->assertTrue(
                str_contains($exception->getMessage(), 'lock timeout')
                || str_contains($exception->getMessage(), '55P03'),
            );
        }

        $this->assertSame(1, DB::table('model_has_roles')->count());
    }
}
