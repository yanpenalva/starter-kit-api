<?php

declare(strict_types=1);

namespace App\Http\Controllers\Roles;

use App\Http\Requests\Roles\IndexRolesRequest;
use App\Http\Requests\Roles\UpdateRolePermissionsRequest;
use App\Http\Resources\Roles\RoleResource;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Support\ApiMessage;
use App\Support\Audit;
use App\Support\Correlation;
use App\Support\Filters\RoleFilters;
use App\Support\PrivilegeTransaction;
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
