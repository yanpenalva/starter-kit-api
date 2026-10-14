<?php

declare(strict_types=1);

namespace Tests\Feature\Coverage;

use App\Enums\Permission;
use App\Http\Requests\Roles\IndexRolesRequest;
use App\Http\Requests\Users\IndexUsersRequest;
use App\Models\User;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concern\ActsAsSanctumUser;
use Tests\TestCase;

/**
 * page()/perPage() das requests de índice paginado: ramo de string numérica e
 * fallback para o default. `+2` é o único input HTTP que alcança o fallback —
 * passa a regra `integer` (FILTER_VALIDATE_INT aceita sinal) e falha
 * ctype_digit, ao contrário das variações com espaço, que o TrimStrings remove.
 */
final class W5RequestPaginationCoverageTest extends TestCase
{
    use ActsAsSanctumUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacCatalogueSeeder::class);
    }

    public function test_users_index_reads_numeric_string_pagination_from_the_query(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersView->value);
        self::actingAsSanctum($actor);

        $this->getJson('/api/v1/users?page=2&per_page=20')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 20);
    }

    public function test_users_index_falls_back_to_defaults_for_signed_numeric_pagination(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersView->value);
        self::actingAsSanctum($actor);

        $this->getJson('/api/v1/users?page=%2B2&per_page=%2B20')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', IndexUsersRequest::DEFAULT_PER_PAGE);
    }

    public function test_roles_index_reads_numeric_string_pagination_from_the_query(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::RolesView->value);
        self::actingAsSanctum($actor);

        $this->getJson('/api/v1/roles?page=2')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_roles_index_falls_back_to_defaults_for_signed_numeric_pagination(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::RolesView->value);
        self::actingAsSanctum($actor);

        $this->getJson('/api/v1/roles?page=%2B2&per_page=%2B20')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', IndexRolesRequest::DEFAULT_PER_PAGE);
    }

    public function test_activity_log_index_falls_back_to_defaults_for_signed_numeric_pagination(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::LogsView->value);
        self::actingAsSanctum($actor);

        $this->getJson('/api/v1/activity-log?page=%2B2&per_page=%2B20')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 15);
    }
}
