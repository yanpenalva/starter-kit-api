<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role as PermissionRole;
use Tests\TestCase;

final class PublicRegistrationDefaultRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_ensures_the_canonical_user_role_without_permissions(): void
    {
        Queue::fake();

        foreach ([
            ['name' => 'Public User One', 'email' => 'public-one@example.test'],
            ['name' => 'Public User Two', 'email' => 'public-two@example.test'],
        ] as $identity) {
            $response = $this->withSession([])->withHeaders([
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ])->postJson('/api/v1/register', $identity + [
                'password' => 'correct-horse-12',
                'password_confirmation' => 'correct-horse-12',
                'roles' => ['admin'],
                'active' => true,
            ])->assertCreated();

            $userId = $response->json('id');
            $this->assertIsInt($userId);
            $user = User::query()->findOrFail($userId);

            $this->assertNull($user->email_verified_at);
            $this->assertNotNull($user->deactivated_at);
        }

        $role = PermissionRole::query()->where('name', 'user')->where('guard_name', 'web')->sole();

        $this->assertSame(1, PermissionRole::query()->where('guard_name', 'web')->count());
        $this->assertDatabaseMissing('roles', ['name' => 'admin', 'guard_name' => 'web']);
        $this->assertSame(0, DB::table('role_has_permissions')->where('role_id', $role->getKey())->count());
        $this->assertSame(2, DB::table('model_has_roles')
            ->where('role_id', $role->getKey())
            ->where('model_type', User::class)
            ->count());
    }
}
