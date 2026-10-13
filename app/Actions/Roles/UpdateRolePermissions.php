<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Actions\Concerns\RecordsPrivilegeAudit;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Support\ApiMessage;
use App\Support\Audit;
use App\Support\PrivilegeTransaction;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

final class UpdateRolePermissions
{
    use RecordsPrivilegeAudit;

    /**
     * @param  list<string>  $permissionNames
     */
    public function run(User $actor, Role $role, array $permissionNames): Role
    {
        PrivilegeTransaction::run(function () use ($actor, $role, $permissionNames): void {
            /** @var Role $target */
            $target = Role::query()->lockForUpdate()->findOrFail($role->getKey());

            if (! app(RolePolicy::class)->updatePermissions($actor, $target, $permissionNames)) {
                abort(Response::HTTP_FORBIDDEN, ApiMessage::UNAUTHORIZED);
            }

            $target->syncPermissions($permissionNames);

            $this->auditRoleChange($actor, $target, Audit::ACTION_ROLE_UPDATE_PERMISSIONS);

            DB::afterCommit(static function (): void {
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            });
        });

        return $role->refresh();
    }
}
