<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Actions\Concerns\RecordsPrivilegeAudit;
use App\Models\User;
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

            if ($target->users()->exists()) {
                abort(422, 'A role assigned to users cannot be deleted.');
            }

            $this->auditRoleChange($actor, $target);
            $target->delete();

            DB::afterCommit(static function (): void {
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            });
        });
    }
}
