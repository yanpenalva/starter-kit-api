<?php

declare(strict_types=1);

namespace App\Http\Controllers\Roles;

use App\Actions\Roles\DestroyRole;
use App\Actions\Roles\IndexRoles;
use App\Actions\Roles\StoreRole;
use App\Actions\Roles\UpdateRolePermissions;
use App\Http\Requests\Roles\IndexRolesRequest;
use App\Http\Requests\Roles\StoreRoleRequest;
use App\Http\Requests\Roles\UpdateRolePermissionsRequest;
use App\Http\Resources\Roles\RoleResource;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Permission\Models\Role;

final class RoleController
{
    use AuthorizesRequests;

    public function __construct(
        private IndexRoles $indexRoles,
        private StoreRole $storeRole,
        private DestroyRole $destroyRole,
        private UpdateRolePermissions $updateRolePermissions,
    ) {}

    /**
     * @return AnonymousResourceCollection<int, RoleResource>
     */
    public function index(IndexRolesRequest $request): AnonymousResourceCollection
    {
        $actor = $request->user();
        assert($actor instanceof User);

        $roles = $this->indexRoles->run($actor, $request->filters(), $request->perPage(), $request->page());

        return RoleResource::collection($roles);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $actor = $request->user();
        assert($actor instanceof User);

        $name = $request->validated('name');
        assert(is_string($name));

        $role = $this->storeRole->run($actor, $name);

        return (new RoleResource($role))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        $actor = $request->user();
        assert($actor instanceof User);

        $this->authorize('delete', $role);

        $this->destroyRole->run($actor, $role);

        return response()->json([], 204);
    }

    public function updatePermissions(UpdateRolePermissionsRequest $request, Role $role): RoleResource
    {
        $actor = $request->user();
        assert($actor instanceof User);

        $this->authorize('updatePermissions', $role);

        return new RoleResource($this->updateRolePermissions->run($actor, $role, $request->permissionNames()));
    }
}
