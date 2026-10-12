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
use Symfony\Component\HttpFoundation\Response;

final class RenameRole
{
    use RecordsPrivilegeAudit;

    public function run(User $actor, Role $role, string $name): Role
    {
        return PrivilegeTransaction::run(function () use ($actor, $role, $name): Role {
            $target = Role::query()->lockForUpdate()->whereKey($role->getKey())->firstOrFail();

            if (! app(RolePolicy::class)->rename($actor, $target)) {
                abort(Response::HTTP_FORBIDDEN, ApiMessage::UNAUTHORIZED);
            }

            $target->name = $name;
            $target->save();
            $this->auditRoleChange($actor, $target);

            DB::afterCommit(static function (): void {
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            });

            return $target->refresh()->load('permissions');
        });
    }
}
