<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Actions\Concerns\RecordsPrivilegeAudit;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Support\ApiMessage;
use App\Support\PrivilegeTransaction;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

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

            if (! app(RolePolicy::class)->updatePermissions($actor, $target)) {
                abort(403, ApiMessage::UNAUTHORIZED);
            }

            $target->syncPermissions($permissionNames);

            $this->auditRoleChange($actor, $target);

            DB::afterCommit(static function (): void {
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            });
        });

        return $role->refresh();
    }
}
