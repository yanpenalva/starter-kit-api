<?php

declare(strict_types=1);

namespace Tests\Feature\Coverage;

use App\Enums\Permission;
use App\Http\Requests\Roles\UpdateRolePermissionsRequest;
use App\Http\Requests\Users\AssignUserRolesRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Models\User;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Conversores de payload (roleNames()/permissionNames()) dos requests de
 * users/roles: caminho validado e fallback defensivo quando validated()
 * devolve um valor fora do contrato. O fallback é inalcançável por input HTTP —
 * as regras `array`/`required` barram o payload antes — e Validator::validated()
 * lança em dado inválido, então o teste injeta o contrato com o valor não-array
 * que a guarda rejeita.
 */
final class W5RequestPayloadConversionCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacCatalogueSeeder::class);
    }

    public function test_assign_user_roles_converts_only_validated_role_names(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::values());

        $request = AssignUserRolesRequest::create('/api/v1/users/1/roles', 'PUT', ['roles' => ['admin', 'user']]);
        $request->setContainer($this->app);
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(static fn (): User => $actor);
        $request->validateResolved();

        $this->assertSame(['admin', 'user'], $request->roleNames());

        $outOfContract = new AssignUserRolesRequest;
        $outOfContract->setValidator($this->outOfContractValidator(['roles' => collect(['admin'])]));

        $this->assertSame([], $outOfContract->roleNames());
    }

    public function test_update_role_permissions_converts_only_validated_permission_names(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::RolesUpdatePermissions->value);

        $request = UpdateRolePermissionsRequest::create('/api/v1/roles/1/permissions', 'PUT', [
            'permissions' => ['roles.view', 'users.view'],
        ]);
        $request->setContainer($this->app);
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(static fn (): User => $actor);
        $request->validateResolved();

        $this->assertSame(['roles.view', 'users.view'], $request->permissionNames());

        $outOfContract = new UpdateRolePermissionsRequest;
        $outOfContract->setValidator($this->outOfContractValidator(['permissions' => collect(['roles.view'])]));

        $this->assertSame([], $outOfContract->permissionNames());
    }

    public function test_store_user_converts_validated_role_names_and_nulls_out_of_contract_payloads(): void
    {
        $actor = User::factory()->verified()->create();
        $actor->givePermissionTo(Permission::values());

        $request = StoreUserRequest::create('/api/v1/users', 'POST', [
            'name' => 'Synthetic Target',
            'email' => 'synthetic.target@example.test',
            'cpf' => '12345678901',
            'password' => 'test-password-01',
            'active' => true,
            'roles' => ['user'],
        ]);
        $request->setContainer($this->app);
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(static fn (): User => $actor);
        $request->validateResolved();

        $this->assertSame(['user'], $request->roleNames());

        $outOfContract = new StoreUserRequest;
        $outOfContract->setValidator($this->outOfContractValidator(['roles' => 'user']));

        $this->assertNull($outOfContract->roleNames());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function outOfContractValidator(array $payload): ValidatorContract
    {
        $validator = $this->createStub(ValidatorContract::class);
        $validator->method('validated')->willReturn($payload);

        return $validator;
    }
}
