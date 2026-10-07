<?php

declare(strict_types=1);

namespace App\Http\Controllers\Roles;

use App\Http\Requests\Roles\IndexRolesRequest;
use App\Http\Requests\Roles\StoreRoleRequest;
use App\Http\Requests\Roles\UpdateRolePermissionsRequest;
use App\Http\Resources\Roles\RoleResource;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Support\ApiMessage;
use App\Support\Audit;
use App\Support\Correlation;
use App\Support\Filters\RoleFilters;
use App\Support\PrivilegeTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Wave B: catalogue role routes. The permission-set mutation consults
 * RolePolicy (admin role immutable) inside PrivilegeTransaction and writes a
 * fail-closed audit record in the same PostgreSQL transaction.
 */
final class RoleController
{
    /**
     * @return AnonymousResourceCollection<int, RoleResource>
     */
    public function index(IndexRolesRequest $request): AnonymousResourceCollection
    {
        $roles = RoleFilters::apply(Role::query(), $request->filters())
            ->with('permissions')
            ->orderBy('id')
            ->paginate($request->perPage(), ['*'], 'page', $request->page());

        return RoleResource::collection($roles);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $actor = $request->user();
        assert($actor instanceof User);
        $name = $request->validated('name');

        $role = PrivilegeTransaction::run(function () use ($actor, $name): Role {
            if (! app(RolePolicy::class)->manage($actor)) {
                abort(403, ApiMessage::UNAUTHORIZED);
            }

            $role = Role::query()->create(['name' => $name, 'guard_name' => 'web']);
            $this->auditRoleChange($actor, $role);

            return $role;
        });

        return (new RoleResource($role))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        $actor = $request->user();
        assert($actor instanceof User);

        PrivilegeTransaction::run(function () use ($actor, $role): void {
            $target = Role::query()->lockForUpdate()->whereKey($role->getKey())->firstOrFail();

            if (! app(RolePolicy::class)->manage($actor)) {
                abort(403, ApiMessage::UNAUTHORIZED);
            }

            if ($target->users()->exists()) {
                abort(422, 'A role assigned to users cannot be deleted.');
            }

            if (! app(RolePolicy::class)->delete($actor, $target)) {
                abort(403, ApiMessage::UNAUTHORIZED);
            }

            $this->auditRoleChange($actor, $target);
            $target->delete();

            DB::afterCommit(static function (): void {
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            });
        });

        return response()->json([], 204);
    }

    public function updatePermissions(UpdateRolePermissionsRequest $request, Role $role): RoleResource
    {
        $actor = $request->user();
        assert($actor instanceof User);
        $permissionNames = $request->permissionNames();

        PrivilegeTransaction::run(function () use ($actor, $role, $permissionNames): void {
            /** @var Role $target */
            $target = Role::query()->lockForUpdate()->findOrFail($role->getKey());

            if (! app(RolePolicy::class)->updatePermissions($actor, $target)) {
                abort(403, ApiMessage::UNAUTHORIZED);
            }

            $target->syncPermissions($permissionNames);

            $this->auditRoleChange($actor, $target);

            DB::afterCommit(static function (): void {
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            });
        });

        return new RoleResource($role->refresh());
    }

    private function auditRoleChange(User $actor, Role $role): void
    {
        $roleKey = $role->getKey();
        assert(is_int($roleKey));
        $actorKey = $actor->getKey();
        assert(is_int($actorKey));

        Audit::record(
            Audit::EVENT_PRIVILEGED_CHANGE,
            'role',
            $roleKey,
            Correlation::current(),
            ['actor_type' => 'user', 'actor_id' => $actorKey],
        );
    }
}
