<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Requests\Roles\IndexRolesRequest;
use App\Http\Requests\Roles\UpdateRolePermissionsRequest;
use App\Http\Requests\Users\AssignUserRolesRequest;
use App\Http\Requests\Users\IndexUsersRequest;
use App\Http\Requests\Users\SetUserStatusRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\Roles\RoleResource;
use App\Http\Resources\Users\UserResource;
use App\Models\User;
use App\Support\Filters\RoleFilters;
use App\Support\Filters\UserFilters;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

final class AdminContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array<int, string>>
     */
    private static function validationErrors(FormRequest $request): array
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::values());
        $request->setUserResolver(static fn (): User => $actor);
        $request->setRedirector(app('redirect'));

        try {
            $request->validateResolved();
        } catch (ValidationException $e) {
            /** @var array<string, array<int, string>> $errors */
            $errors = $e->errors();

            return $errors;
        }

        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.default');
        $this->assertContains($connection, ['pgsql', 'sqlite']);

        if ($connection === 'pgsql') {
            $database = config('database.connections.pgsql.database');
            $this->assertIsString($database);
            $this->assertSame($database, DB::connection()->getDatabaseName());
            $this->assertSame('starter_api_test', $database);
        }

        $this->seed(RbacCatalogueSeeder::class);
    }

    public function test_index_users_authorization_requires_the_users_view_gate(): void
    {
        $request = IndexUsersRequest::create('/api/v1/users', 'GET', []);
        $request->setContainer($this->app);

        $eligible = User::factory()->verified()->create();
        $eligible->givePermissionTo(Permission::UsersView->value);
        $request->setUserResolver(static fn (): User => $eligible);
        $this->assertTrue($request->authorize());

        $unprivileged = User::factory()->verified()->create();
        $request->setUserResolver(static fn (): User => $unprivileged);
        $this->assertFalse($request->authorize());

        $unverified = User::factory()->unverified()->create();
        $unverified->givePermissionTo(Permission::UsersView->value);
        $request->setUserResolver(static fn (): User => $unverified);
        $this->assertFalse($request->authorize());
    }

    public function test_index_roles_authorization_requires_the_roles_view_gate(): void
    {
        $request = IndexRolesRequest::create('/api/v1/roles', 'GET', []);
        $request->setContainer($this->app);

        $eligible = User::factory()->verified()->create();
        $eligible->givePermissionTo(Permission::RolesView->value);
        $request->setUserResolver(static fn (): User => $eligible);
        $this->assertTrue($request->authorize());

        $unprivileged = User::factory()->verified()->create();
        $request->setUserResolver(static fn (): User => $unprivileged);
        $this->assertFalse($request->authorize());
    }

    public function test_index_users_validation_bounds_pagination_and_filters(): void
    {
        $request = IndexUsersRequest::create('/api/v1/users', 'GET', [
            'page' => 0,
            'per_page' => 101,
            'status' => 'bogus',
            'search' => 123,
        ]);
        $request->setContainer($this->app);

        $errors = self::validationErrors($request);
        $this->assertSame(['page', 'per_page', 'search', 'status'], array_keys($errors));

        $valid = IndexUsersRequest::create('/api/v1/users', 'GET', [
            'page' => 2,
            'per_page' => 25,
            'search' => 'ana',
            'status' => 'active',
            'role' => 'admin',
            'unlisted' => 'ignored',
        ]);
        $valid->setContainer($this->app);
        $this->assertSame([], self::validationErrors($valid));

        $this->assertSame(2, $valid->page());
        $this->assertSame(25, $valid->perPage());
        $this->assertSame(
            ['search' => 'ana', 'status' => 'active', 'role' => 'admin'],
            $valid->filters(),
        );
    }

    public function test_index_users_defaults_apply_when_pagination_is_absent(): void
    {
        $request = IndexUsersRequest::create('/api/v1/users', 'GET', []);
        $request->setContainer($this->app);

        $this->assertSame([], self::validationErrors($request));
        $this->assertSame(1, $request->page());
        $this->assertSame(IndexUsersRequest::DEFAULT_PER_PAGE, $request->perPage());
        $this->assertSame([], $request->filters());
    }

    public function test_update_user_validation_pins_the_input_shape(): void
    {
        $request = UpdateUserRequest::create('/api/v1/users', 'PATCH', [
            'email' => 'not-an-email',
            'cpf' => '123',
        ]);
        $request->setContainer($this->app);

        $errors = self::validationErrors($request);
        $this->assertSame(['email', 'cpf'], array_keys($errors));

        $valid = UpdateUserRequest::create('/api/v1/users', 'PATCH', [
            'name' => 'Novo Nome',
            'email' => 'novo@example.test',
            'cpf' => null,
        ]);
        $valid->setContainer($this->app);
        $this->assertSame([], self::validationErrors($valid));
    }

    public function test_set_user_status_only_accepts_known_transitions(): void
    {
        $missing = SetUserStatusRequest::create('/api/v1/users/1/status', 'POST', []);
        $missing->setContainer($this->app);
        $this->assertNotEmpty(self::validationErrors($missing));

        $bogus = SetUserStatusRequest::create('/api/v1/users/1/status', 'POST', ['status' => 'bogus']);
        $bogus->setContainer($this->app);
        $this->assertNotEmpty(self::validationErrors($bogus));

        $valid = SetUserStatusRequest::create('/api/v1/users/1/status', 'POST', ['status' => 'deactivated']);
        $valid->setContainer($this->app);
        $this->assertSame([], self::validationErrors($valid));
        $this->assertSame('deactivated', $valid->status());
    }

    public function test_assign_user_roles_only_accepts_catalogue_roles(): void
    {
        $empty = AssignUserRolesRequest::create('/api/v1/users/1/roles', 'PUT', ['roles' => []]);
        $empty->setContainer($this->app);
        $this->assertNotEmpty(self::validationErrors($empty));

        $ghost = AssignUserRolesRequest::create('/api/v1/users/1/roles', 'PUT', ['roles' => ['ghost']]);
        $ghost->setContainer($this->app);
        $this->assertNotEmpty(self::validationErrors($ghost));

        $duplicated = AssignUserRolesRequest::create('/api/v1/users/1/roles', 'PUT', ['roles' => ['user', 'user']]);
        $duplicated->setContainer($this->app);
        $this->assertNotEmpty(self::validationErrors($duplicated));

        $valid = AssignUserRolesRequest::create('/api/v1/users/1/roles', 'PUT', ['roles' => ['admin', 'user']]);
        $valid->setContainer($this->app);
        $this->assertSame([], self::validationErrors($valid));
        $this->assertSame(['admin', 'user'], $valid->roleNames());
    }

    public function test_update_role_permissions_only_accepts_catalogue_permissions(): void
    {
        $rogue = UpdateRolePermissionsRequest::create('/api/v1/roles/1/permissions', 'PUT', [
            'permissions' => ['users.delete'],
        ]);
        $rogue->setContainer($this->app);
        $this->assertNotEmpty(self::validationErrors($rogue));

        $valid = UpdateRolePermissionsRequest::create('/api/v1/roles/1/permissions', 'PUT', [
            'permissions' => ['roles.view', 'users.view'],
        ]);
        $valid->setContainer($this->app);
        $this->assertSame([], self::validationErrors($valid));
        $this->assertSame(['roles.view', 'users.view'], $valid->permissionNames());
    }

    public function test_user_filters_apply_allowlisted_search_status_and_role(): void
    {
        $alice = User::factory()->verified()->create(['name' => 'Alice', 'email' => 'alice@example.test']);
        $alice->assignRole(Role::User->value);

        $bob = User::factory()->deactivated()->create(['name' => 'Bob', 'email' => 'bob@example.test']);
        $bob->assignRole(Role::Admin->value);

        User::factory()->verified()->create(['name' => 'Carol', 'email' => 'carol@other.test']);

        $this->assertSame([$alice->id], UserFilters::apply(User::query(), ['search' => 'ali'])->pluck('id')->all());
        $this->assertSame([$bob->id], UserFilters::apply(User::query(), ['status' => 'deactivated'])->pluck('id')->all());
        $this->assertSame([$bob->id], UserFilters::apply(User::query(), ['role' => 'admin'])->pluck('id')->all());
        $this->assertSame(
            [$alice->id, $bob->id],
            UserFilters::apply(User::query(), ['search' => 'example.test'])->orderBy('id')->pluck('id')->all(),
        );

        $this->assertSame(
            [$alice->id],
            UserFilters::apply(User::query(), ['unknown' => 'alice', 'search' => 'ali'])->pluck('id')->all(),
        );
    }

    public function test_user_filters_escape_like_wildcards_literally(): void
    {
        $literal = User::factory()->verified()->create(['name' => '100% Legit']);
        User::factory()->verified()->create(['name' => '1000 Legit']);

        $this->assertSame(
            [$literal->id],
            UserFilters::apply(User::query(), ['search' => '0% Leg'])->pluck('id')->all(),
        );
    }

    public function test_role_filters_apply_the_allowlisted_search(): void
    {
        $this->assertSame(
            ['admin'],
            RoleFilters::apply(RoleModel::query(), ['search' => 'adm'])->pluck('name')->all(),
        );
    }

    public function test_user_resource_omits_cpf_without_the_read_cpf_permission(): void
    {
        $target = User::factory()->verified()->withCpf()->create();

        $unprivileged = User::factory()->verified()->create();
        $request = Request::create('/api/v1/users', 'GET');
        $request->setUserResolver(static fn (): User => $unprivileged);

        $payload = (new UserResource($target))->resolve($request);
        $this->assertArrayNotHasKey('cpf', $payload);
        $this->assertSame($target->id, $payload['id']);
        $this->assertSame($target->email, $payload['email']);
        $this->assertIsString($payload['email_verified_at']);
        $this->assertNull($payload['deactivated_at']);
        $this->assertSame([], $payload['roles']);
        $this->assertIsObject($payload['role_display_names']);
        $this->assertSame([], (array) $payload['role_display_names']);

        $reader = User::factory()->verified()->create();
        $reader->givePermissionTo(Permission::UsersReadCpf->value);
        $request->setUserResolver(static fn (): User => $reader);

        $payload = (new UserResource($target))->resolve($request);
        $this->assertSame($target->cpf, $payload['cpf']);

        $unverified = User::factory()->unverified()->create();
        $unverified->givePermissionTo(Permission::UsersReadCpf->value);
        $request->setUserResolver(static fn (): User => $unverified);

        $payload = (new UserResource($target))->resolve($request);
        $this->assertArrayNotHasKey('cpf', $payload);
    }

    public function test_role_resource_exposes_the_sorted_permission_set(): void
    {
        $admin = RoleModel::where('name', Role::Admin->value)->firstOrFail();

        $payload = (new RoleResource($admin))->resolve(Request::create('/api/v1/roles', 'GET'));

        $this->assertSame($admin->id, $payload['id']);
        $this->assertSame('admin', $payload['name']);
        $this->assertSame('web', $payload['guard_name']);

        $expected = Permission::values();
        sort($expected);

        $permissions = $payload['permissions'];
        $this->assertIsArray($permissions);
        $this->assertSame($expected, array_values($permissions));
    }

    public function test_paginated_resource_collections_pin_the_envelope_shape(): void
    {
        $first = User::factory()->verified()->create();
        $second = User::factory()->verified()->create();

        $paginator = new LengthAwarePaginator([$first, $second], 2, IndexUsersRequest::DEFAULT_PER_PAGE, 1);

        $response = UserResource::collection($paginator)->response();
        $payload = $response->getData(true);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('links', $payload);
        $this->assertArrayHasKey('meta', $payload);

        $meta = $payload['meta'];
        $this->assertIsArray($meta);
        $this->assertSame(1, $meta['current_page']);
        $this->assertSame(IndexUsersRequest::DEFAULT_PER_PAGE, $meta['per_page']);
        $this->assertSame(2, $meta['total']);

        $data = $payload['data'];
        $this->assertIsArray($data);
        $this->assertCount(2, $data);
    }
}
