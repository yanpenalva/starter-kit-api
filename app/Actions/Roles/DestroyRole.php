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

final class DestroyRole
{
    use RecordsPrivilegeAudit;

    public function run(User $actor, Role $role): void
    {
        PrivilegeTransaction::runWithActor($actor, function (User $actor) use ($role): void {
            $target = Role::query()->lockForUpdate()->whereKey($role->getKey())->firstOrFail();

            if (! app(RolePolicy::class)->delete($actor, $target)) {
                abort(Response::HTTP_FORBIDDEN, ApiMessage::UNAUTHORIZED);
            }

            if ($target->users()->exists()) {
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'A role assigned to users cannot be deleted.');
            }

            $this->auditRoleChange($actor, $target, Audit::ACTION_ROLE_DESTROY);
            $target->delete();

            DB::afterCommit(static function (): void {
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            });
        });
    }
}
