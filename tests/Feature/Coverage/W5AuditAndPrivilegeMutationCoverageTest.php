<?php

declare(strict_types=1);

namespace Tests\Feature\Coverage;

use App\Actions\Users\UpdateUser;
use App\Enums\Permission;
use App\Http\Resources\Audit\ActivityResource;
use App\Models\User;
use App\Support\Audit;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Guardas de auditoria e de mutação privilegiada: evento fora da allowlist,
 * registro de auditoria que não persiste, created_at ausente no feed e a
 * reavaliação de credenciais sobre a linha travada (stale read do alvo).
 */
final class W5AuditAndPrivilegeMutationCoverageTest extends TestCase
{
    use RefreshDatabase;

    private const SYNTHETIC_SUBJECT_ID = 987654;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacCatalogueSeeder::class);
    }

    public function test_audit_record_rejects_an_event_outside_the_allowlist(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Audit event is not approved: rogue.event');

        Audit::record('rogue.event', 'user', self::SYNTHETIC_SUBJECT_ID, (string) Str::uuid(), [
            'actor_type' => 'user',
            'actor_id' => 1,
            'action' => Audit::ACTION_USER_UPDATE,
        ]);
    }

    public function test_audit_record_fails_when_the_entry_is_not_persisted(): void
    {
        // Expurgo concorrente da linha logo após o INSERT: sem registro
        // persistido, a mutação privilegiada não pode prosseguir.
        $event = 'eloquent.created: '.Activity::class;
        Event::listen($event, static function (Activity $activity): void {
            Activity::query()->whereKey($activity->getKey())->delete();
        });

        $caught = null;

        try {
            Audit::record(
                Audit::EVENT_PRIVILEGED_CHANGE,
                'user',
                self::SYNTHETIC_SUBJECT_ID,
                (string) Str::uuid(),
                ['actor_type' => 'user', 'actor_id' => 1, 'action' => Audit::ACTION_USER_UPDATE],
            );
        } catch (RuntimeException $exception) {
            $caught = $exception;
        } finally {
            Event::forget($event);
        }

        $this->assertInstanceOf(RuntimeException::class, $caught);
        $this->assertSame('Audit record was not persisted; the mutation must not stand.', $caught->getMessage());
        $this->assertDatabaseMissing('activity_log', [
            'event' => Audit::EVENT_PRIVILEGED_CHANGE,
            'subject_id' => self::SYNTHETIC_SUBJECT_ID,
        ]);
    }

    public function test_activity_resource_serializes_a_missing_created_at_as_null(): void
    {
        $activity = Activity::query()->create([
            'log_name' => 'privilege',
            'description' => Audit::EVENT_PRIVILEGED_CHANGE,
            'event' => Audit::EVENT_PRIVILEGED_CHANGE,
            'subject_type' => 'user',
            'subject_id' => self::SYNTHETIC_SUBJECT_ID,
            'properties' => ['action' => Audit::ACTION_USER_UPDATE],
        ]);

        // A coluna created_at é anulável: linhas históricas/importadas podem
        // não ter timestamp, e o envelope promete string|null.
        Activity::query()->whereKey($activity->getKey())->update(['created_at' => null]);
        $activity->refresh();

        $request = Request::create('/api/v1/activity-log', 'GET');
        $payload = (new ActivityResource($activity))->resolve($request);

        $this->assertNull($activity->created_at);
        $this->assertNull($payload['created_at']);
        $this->assertSame($activity->getKey(), $payload['id']);
        $this->assertSame(Audit::EVENT_PRIVILEGED_CHANGE, $payload['event']);
        $this->assertSame(['action' => Audit::ACTION_USER_UPDATE], $payload['properties']);
    }

    public function test_update_user_rechecks_credentials_privilege_on_the_locked_row(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersUpdate->value);

        $target = User::factory()->verified()->create(['name' => 'Synthetic Target']);
        $originalPassword = $target->password;

        // Snapshot lido antes da mutação concorrente que concede o privilégio:
        // a guarda interna precisa reavaliar a política sobre a linha travada.
        $staleKey = $target->getKey();
        $this->assertIsInt($staleKey);
        $stale = User::query()->with(['roles', 'permissions'])->findOrFail($staleKey);
        $target->givePermissionTo(Permission::RolesManage->value);

        $this->assertActionStatus(403, static fn (): mixed => app(UpdateUser::class)->run(
            $actor,
            $stale,
            ['name' => 'Renamed', 'password' => 'hijacked-password-12'],
        ));

        $fresh = $target->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('Synthetic Target', $fresh->name);
        $this->assertSame($originalPassword, $fresh->password);
    }

    /**
     * @param  callable(): mixed  $call
     */
    private function assertActionStatus(int $status, callable $call): void
    {
        try {
            $call();
        } catch (HttpException $exception) {
            $this->assertSame($status, $exception->getStatusCode());

            return;
        }

        $this->fail('Expected an HTTP error response.');
    }
}
