<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concern\ActsAsSanctumUser;
use Tests\TestCase;

final class SessionProfileAuthorityTest extends TestCase
{
    use ActsAsSanctumUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacCatalogueSeeder::class);
    }

    public function test_profile_identity_and_authorities_come_only_from_the_authenticated_session(): void
    {
        $actor = User::factory()->create(['email_verified_at' => now()]);
        $actor->assignRole(Role::User->value);

        $other = User::factory()->create(['email_verified_at' => now()]);
        $other->assignRole(Role::Admin->value);

        self::actingAsSanctum(User::query()->whereKey($actor->getKey())->firstOrFail());

        $response = $this->getJson('/api/v1/auth/my-profile?user_id='.$other->id.'&role=admin');

        $response->assertOk()
            ->assertJsonPath('data.id', $actor->id)
            ->assertJsonPath('data.roles', [Role::User->value])
            ->assertJsonPath('data.permissions', []);
    }
}
