<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Http\Requests\Audit\IndexActivitiesRequest;
use App\Models\User;
use App\Support\Audit;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\Concern\ActsAsSanctumUser;
use Tests\TestCase;

/**
 * Wave B: HTTP slice of the audit feed. The read path requires the
 * logs.view gate with eligibility, filters stay allowlisted and bounded,
 * and every exposed field comes from the approved minimal envelope — no
 * PII, no invented events, no raw payloads.
 */
final class AuditFeedTest extends TestCase
{
    use ActsAsSanctumUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.default');
        $this->assertContains($connection, ['pgsql', 'sqlite']);

        if ($connection === 'pgsql') {
            $database = config('database.connections.pgsql.database');
            $this->assertIsString($database);
            $this->assertSame('starter_api_test', $database);
        }

        $this->seed(RbacCatalogueSeeder::class);
    }

    public function test_anonymous_feed_request_returns_401(): void
    {
        $this->getJson('/api/v1/activity-log')->assertStatus(401);
    }

    public function test_feed_requires_the_logs_view_gate_with_eligibility(): void
    {
        $unprivileged = User::factory()->verified()->create();

        self::actingAsSanctum($unprivileged);
        $this->getJson('/api/v1/activity-log')
            ->assertStatus(403)
            ->assertExactJson(['message' => 'Acesso não autorizado.']);

        $unverified = User::factory()->unverified()->create();
        $unverified->givePermissionTo(Permission::LogsView->value);

        self::actingAsSanctum($unverified);
        $this->getJson('/api/v1/activity-log')
            ->assertStatus(403);

        $deactivated = User::factory()->deactivated()->create();
        $deactivated->givePermissionTo(Permission::LogsView->value);

        self::actingAsSanctum($deactivated);
        $this->getJson('/api/v1/activity-log')
            ->assertStatus(403);
    }

    public function test_index_activity_request_authorizes_through_the_logs_view_gate(): void
    {
        $request = IndexActivitiesRequest::create('/api/v1/activity-log', 'GET', []);
        $request->setContainer($this->app);

        $eligible = User::factory()->verified()->create();
        $eligible->givePermissionTo(Permission::LogsView->value);
        $request->setUserResolver(static fn (): User => $eligible);
        $this->assertTrue($request->authorize());

        $unprivileged = User::factory()->verified()->create();
        $request->setUserResolver(static fn (): User => $unprivileged);
        $this->assertFalse($request->authorize());

        $unverified = User::factory()->unverified()->create();
        $unverified->givePermissionTo(Permission::LogsView->value);
        $request->setUserResolver(static fn (): User => $unverified);
        $this->assertFalse($request->authorize());
    }

    public function test_feed_exposes_the_minimal_envelope_without_pii(): void
    {
        $target = User::factory()->verified()->withCpf()->create([
            'email' => 'auditado@example.test',
        ]);

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo([Permission::UsersSetStatus->value, Permission::LogsView->value]);

        self::actingAsSanctum($actor);
        $this->postJson("/api/v1/users/{$target->id}/status", ['status' => 'deactivated'])
            ->assertOk();

        self::actingAsSanctum($actor);
        $response = $this->getJson('/api/v1/activity-log');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'event',
                    'subject_type',
                    'subject_id',
                    'description',
                    'properties',
                    'created_at',
                ]],
                'links',
                'meta',
            ]);

        $entries = $response->json('data');
        $this->assertIsArray($entries);
        $this->assertNotEmpty($entries);

        $mutation = null;

        foreach ($entries as $entry) {
            $this->assertIsArray($entry);
            $this->assertContains($entry['event'], Audit::allowedEvents());
            $this->assertContains($entry['subject_type'], ['user', 'role']);
            $this->assertSame(Audit::EVENT_PRIVILEGED_CHANGE, $entry['description']);
            /** @var array<string, mixed> $entryProperties */
            $entryProperties = $entry['properties'];
            $entryCorrelationId = $entryProperties['correlation_id'];
            $this->assertIsString($entryCorrelationId);
            $this->assertTrue(Str::isUuid($entryCorrelationId));
            $entryCreatedAt = $entry['created_at'];
            $this->assertIsString($entryCreatedAt);
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
                $entryCreatedAt,
            );

            if ($entry['subject_id'] === $target->id) {
                $mutation = $entry;
            }
        }

        $this->assertIsArray($mutation, 'The mutation audit entry must be listed.');
        $this->assertSame('user', $mutation['subject_type']);
        /** @var array<string, mixed> $mutationProperties */
        $mutationProperties = $mutation['properties'];
        $this->assertSame('user', $mutationProperties['actor_type']);
        $this->assertSame((string) $actor->id, $mutationProperties['actor_id']);

        $this->assertStringNotContainsString('auditado@example.test', (string) $response->getContent());
        $this->assertStringNotContainsString((string) $target->cpf, (string) $response->getContent());
    }

    public function test_feed_redacts_sensitive_properties_written_outside_the_audit_writer(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::LogsView->value);
        Activity::query()->create([
            'log_name' => 'privilege',
            'description' => 'Sensitive user data for person@example.test',
            'event' => Audit::EVENT_PRIVILEGED_CHANGE,
            'subject_type' => User::class,
            'subject_id' => $actor->id,
            'causer_type' => User::class,
            'causer_id' => $actor->id,
            'properties' => [
                'correlation_id' => 'person@example.test',
                'email' => 'person@example.test',
                'cpf' => '12345678901',
                'secret' => 'hidden-value',
                'actor_type' => 'person@example.test',
                'actor_id' => 'person@example.test',
            ],
        ]);

        self::actingAsSanctum($actor);
        $response = $this->getJson('/api/v1/activity-log')->assertOk();
        $content = (string) $response->getContent();
        $this->assertStringNotContainsString('person@example.test', $content);
        $this->assertStringNotContainsString('12345678901', $content);
        $this->assertStringNotContainsString('hidden-value', $content);
    }

    public function test_feed_filters_writer_generated_mutations_by_responsible_actor(): void
    {
        $target = User::factory()->verified()->create();
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo([Permission::UsersSetStatus->value, Permission::LogsView->value]);

        self::actingAsSanctum($actor);
        $this->postJson("/api/v1/users/{$target->id}/status", ['status' => 'deactivated'])->assertOk();
        $activity = Activity::query()->where('subject_id', $target->id)->firstOrFail();
        $this->assertSame($actor->id, $activity->causer_id);

        self::actingAsSanctum($actor);
        $this->getJson('/api/v1/activity-log?causer_id='.$actor->id)
            ->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_feed_applies_allowlisted_filters_and_ignores_unknown_keys(): void
    {
        $target = User::factory()->verified()->create();

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo([Permission::UsersSetStatus->value, Permission::LogsView->value]);

        self::actingAsSanctum($actor);
        $this->postJson("/api/v1/users/{$target->id}/status", ['status' => 'deactivated'])
            ->assertOk();

        self::actingAsSanctum($actor);
        $byEvent = $this->getJson('/api/v1/activity-log?event='.Audit::EVENT_PRIVILEGED_CHANGE);

        $byEvent->assertOk();
        /** @var array<int, array{event: string}> $byEventEntries */
        $byEventEntries = $byEvent->json('data');
        foreach ($byEventEntries as $entry) {
            $this->assertSame(Audit::EVENT_PRIVILEGED_CHANGE, $entry['event']);
        }

        self::actingAsSanctum($actor);
        $bySubject = $this->getJson('/api/v1/activity-log?subject_type=user');

        $bySubject->assertOk();
        /** @var array<int, array{subject_type: string}> $bySubjectEntries */
        $bySubjectEntries = $bySubject->json('data');
        foreach ($bySubjectEntries as $entry) {
            $this->assertSame('user', $entry['subject_type']);
        }

        self::actingAsSanctum($actor);
        $this->getJson('/api/v1/activity-log?unlisted=bogus')
            ->assertOk();

        self::actingAsSanctum($actor);
        $this->getJson('/api/v1/activity-log?event=rogue.event')
            ->assertStatus(422)
            ->assertJsonValidationErrors('event');

        self::actingAsSanctum($actor);
        $this->getJson('/api/v1/activity-log?per_page=101')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    public function test_feed_filters_by_date_range_and_responsible_user(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::LogsView->value);
        $otherActor = User::factory()->verified()->create();
        $this->createAuditEntry($actor, '2026-10-01 12:00:00');
        $this->createAuditEntry($actor, '2026-10-04 12:00:00');
        $this->createAuditEntry($otherActor, '2026-10-03 12:00:00');

        self::actingAsSanctum($actor);
        $response = $this->getJson('/api/v1/activity-log?from=2026-10-02&to=2026-10-04&causer_id='.$actor->id);

        $response->assertOk()->assertJsonPath('meta.total', 1);
        $entries = $response->json('data');
        $this->assertIsArray($entries);
        $this->assertCount(1, $entries);
        $entry = $entries[0] ?? null;
        $this->assertIsArray($entry);
        $this->assertSame('2026-10-04T12:00:00Z', $entry['created_at'] ?? null);
    }

    public function test_feed_validates_period_and_responsible_filters(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::LogsView->value);
        self::actingAsSanctum($actor);

        $this->getJson('/api/v1/activity-log?from=not-a-date')
            ->assertStatus(422)
            ->assertJsonValidationErrors('from');

        $this->getJson('/api/v1/activity-log?from=2026-10-05&to=2026-10-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('to');

        $this->getJson('/api/v1/activity-log?causer_id=invalid')
            ->assertStatus(422)
            ->assertJsonValidationErrors('causer_id');
    }

    public function test_activity_log_has_composite_event_id_index(): void
    {
        $indexes = Schema::getIndexes('activity_log');
        $compositeIndex = collect($indexes)->first(static function (mixed $index): bool {
            return is_array($index) && ($index['columns'] ?? []) === ['event', 'id'];
        });

        $this->assertNotNull($compositeIndex);
    }

    private function createAuditEntry(User $causer, string $createdAt): void
    {
        Activity::query()->create([
            'log_name' => 'privilege',
            'description' => Audit::EVENT_PRIVILEGED_CHANGE,
            'event' => Audit::EVENT_PRIVILEGED_CHANGE,
            'subject_type' => User::class,
            'subject_id' => $causer->id,
            'causer_type' => User::class,
            'causer_id' => $causer->id,
            'properties' => [],
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    public function test_feed_paginates_with_bounded_page_sizes(): void
    {
        $first = User::factory()->verified()->create();
        $second = User::factory()->verified()->create();

        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo([Permission::UsersSetStatus->value, Permission::LogsView->value]);

        self::actingAsSanctum($actor);
        $this->postJson("/api/v1/users/{$first->id}/status", ['status' => 'deactivated'])
            ->assertOk();

        self::actingAsSanctum($actor);
        $this->postJson("/api/v1/users/{$second->id}/status", ['status' => 'deactivated'])
            ->assertOk();

        self::actingAsSanctum($actor);
        $page = $this->getJson('/api/v1/activity-log?per_page=1&page=2');

        $page->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(1, 'data');
    }
}
