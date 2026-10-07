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

final class DestroyRole
{
    use RecordsPrivilegeAudit;

    public function run(User $actor, Role $role): void
    {
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
    }
}
