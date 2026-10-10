<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concern\ActsAsSanctumUser;
use Tests\TestCase;

final class EmptyIndexFiltersTest extends TestCase
{
    use ActsAsSanctumUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacCatalogueSeeder::class);
    }

    public function test_users_index_accepts_empty_filters(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo('users.view');
        self::actingAsSanctum($actor);

        $this->getJson('/api/v1/users?search=&status=&role=&per_page=15')->assertOk();
    }

    public function test_users_index_rejects_invalid_non_empty_filter(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo('users.view');
        self::actingAsSanctum($actor);

        $this->getJson('/api/v1/users?status=not-a-status')->assertUnprocessable();
    }

    public function test_roles_index_accepts_empty_search(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo('roles.view');
        self::actingAsSanctum($actor);

        $this->getJson('/api/v1/roles?search=&per_page=15')->assertOk();
    }

    public function test_activity_log_index_accepts_empty_filters(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo('logs.view');
        self::actingAsSanctum($actor);

        $this->getJson('/api/v1/activity-log?event=&subject_type=&from=&to=&per_page=15')->assertOk();
    }

    public function test_activity_log_index_rejects_invalid_non_empty_filter(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo('logs.view');
        self::actingAsSanctum($actor);

        $this->getJson('/api/v1/activity-log?event=not-an-event')->assertUnprocessable();
    }
}
