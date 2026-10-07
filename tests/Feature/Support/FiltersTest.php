<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Enums\Role;
use App\Models\User;
use App\Support\Audit;
use App\Support\Filters\ActivityFilters;
use App\Support\Filters\RoleFilters;
use App\Support\Filters\UserFilters;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

final class FiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacCatalogueSeeder::class);
    }

    public function test_user_search_treats_like_wildcards_as_literals(): void
    {
        $literal = User::factory()->verified()->create(['name' => '0% Leg_it', 'email' => 'literal@example.test']);
        User::factory()->verified()->create(['name' => 'Alice', 'email' => 'alice@example.test']);

        $this->assertSame(
            [$literal->id],
            UserFilters::apply(User::query(), ['search' => '0% Leg'])->pluck('id')->all()
        );

        $this->assertSame(
            [$literal->id],
            UserFilters::apply(User::query(), ['search' => 'leg_it'])->pluck('id')->all()
        );

        $this->assertSame(
            [$literal->id],
            UserFilters::apply(User::query(), ['search' => '0% Leg_it'])->pluck('id')->all()
        );
    }

    public function test_user_search_matches_name_and_email_case_insensitively(): void
    {
        $alice = User::factory()->verified()->create(['name' => 'Alice', 'email' => 'alice@example.test']);
        User::factory()->verified()->create(['name' => 'Bob', 'email' => 'bob@other.test']);

        $this->assertSame([$alice->id], UserFilters::apply(User::query(), ['search' => 'ALICE'])->pluck('id')->all());
        $this->assertSame([$alice->id], UserFilters::apply(User::query(), ['search' => 'example.test'])->pluck('id')->all());
    }

    public function test_user_filters_combine_status_role_and_ignore_unknown_keys(): void
    {
        $alice = User::factory()->verified()->create(['name' => 'Alice']);
        $alice->assignRole(Role::User->value);
        $bob = User::factory()->deactivated()->create(['name' => 'Bob']);

        $this->assertSame([$bob->id], UserFilters::apply(User::query(), ['status' => 'deactivated'])->pluck('id')->all());
        $this->assertSame([$alice->id], UserFilters::apply(User::query(), ['role' => Role::User->value])->pluck('id')->all());

        $this->assertSame(
            [$alice->id],
            UserFilters::apply(User::query(), ['unknown' => 'alice', 'search' => 'Alice'])->pluck('id')->all()
        );

        $this->assertSame(2, UserFilters::apply(User::query(), ['status' => 'bogus'])->count());
        $this->assertSame(2, UserFilters::apply(User::query(), ['search' => ''])->count());
    }

    public function test_role_search_matches_names_case_insensitively(): void
    {
        $this->assertSame(
            [Role::Admin->value],
            RoleFilters::apply(RoleModel::query(), ['search' => 'ADM'])->pluck('name')->all()
        );

        $this->assertSame(
            [Role::Admin->value, Role::User->value],
            RoleFilters::apply(RoleModel::query(), [])->orderBy('id')->pluck('name')->all()
        );
    }

    public function test_activity_filters_apply_every_allowlisted_key(): void
    {
        $actor = User::factory()->verified()->create();
        $other = User::factory()->verified()->create();
        $otherId = $other->getKey();
        $this->assertIsInt($otherId);
        $actorId = $actor->getKey();
        $this->assertIsInt($actorId);

        Audit::record(
            Audit::EVENT_PRIVILEGED_CHANGE,
            'user',
            $otherId,
            '123e4567-e89b-42d3-a456-426614174000',
            ['actor_type' => 'user', 'actor_id' => $actorId]
        );

        $role = RoleModel::where('name', Role::User->value)->firstOrFail();
        $roleId = $role->getKey();
        $this->assertIsInt($roleId);
        Audit::record(
            Audit::EVENT_PRIVILEGED_CHANGE,
            'role',
            $roleId,
            '123e4567-e89b-42d3-a456-426614174001',
            ['actor_type' => 'user', 'actor_id' => $otherId]
        );

        $this->assertSame(2, ActivityFilters::apply(Activity::query(), [])->count());

        $this->assertSame(
            2,
            ActivityFilters::apply(Activity::query(), ['event' => Audit::EVENT_PRIVILEGED_CHANGE])->count()
        );

        $this->assertSame(1, ActivityFilters::apply(Activity::query(), ['subject_type' => 'role'])->count());
        $this->assertSame(
            1,
            ActivityFilters::apply(Activity::query(), ['causer_id' => (string) $actorId])->count()
        );

        $today = now()->format('Y-m-d');
        $this->assertSame(2, ActivityFilters::apply(Activity::query(), ['from' => $today, 'to' => $today])->count());
        $this->assertSame(0, ActivityFilters::apply(Activity::query(), ['from' => '2999-01-01'])->count());

        $this->assertSame(
            2,
            ActivityFilters::apply(Activity::query(), ['unknown' => 'bogus', 'event' => Audit::EVENT_PRIVILEGED_CHANGE])->count()
        );
    }
}
