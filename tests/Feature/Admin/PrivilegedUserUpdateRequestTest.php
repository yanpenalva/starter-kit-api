<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Tests\TestCase;

final class PrivilegedUserUpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacCatalogueSeeder::class);
    }

    public function test_update_request_denies_credentials_for_a_lower_privilege_actor(): void
    {
        $target = User::factory()->verified()->create();
        $target->givePermissionTo(Permission::RolesManage->value);
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::UsersUpdate->value);
        $request = $this->request($actor, $target, 'password', 'hijacked-password-12');

        $this->expectException(AuthorizationException::class);
        $request->validateResolved();
    }

    public function test_update_request_allows_an_admin_role_operator(): void
    {
        $target = User::factory()->verified()->create();
        $target->assignRole(Role::Admin->value);
        $actor = User::factory()->verified()->create();
        $actor->assignRole(Role::Admin->value);
        $request = $this->request($actor, $target, 'email', 'admin-updated@example.test');

        $request->validateResolved();

        $this->assertSame('admin-updated@example.test', $request->validated()['email']);
    }

    public function test_update_request_allows_a_users_manage_equivalent_operator(): void
    {
        $target = User::factory()->verified()->create();
        $target->assignRole(Role::Admin->value);
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo([Permission::UsersUpdate->value, Permission::UsersManage->value]);
        $request = $this->request($actor, $target, 'email', 'manager-updated@example.test');

        $request->validateResolved();

        $this->assertSame('manager-updated@example.test', $request->validated()['email']);
    }

    private function request(User $actor, User $target, string $field, string $value): UpdateUserRequest
    {
        $request = UpdateUserRequest::create('/api/v1/users/'.$target->id, 'PATCH', [$field => $value]);
        $request->setContainer($this->app);
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(static fn (): User => $actor);

        $route = new Route(['PATCH'], '/api/v1/users/{user}', static function (): void {});
        $route->bind($request);
        $route->setParameter('user', $target);
        $request->setRouteResolver(static fn (): Route => $route);

        return $request;
    }
}
