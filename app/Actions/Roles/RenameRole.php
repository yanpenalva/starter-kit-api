<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Actions\Concerns\RecordsPrivilegeAudit;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Support\ApiMessage;
use App\Support\Audit;
use App\Support\PrivilegeTransaction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

final class RenameRole
{
    use RecordsPrivilegeAudit;

    public function run(User $actor, Role $role, string $name): Role
    {
        return PrivilegeTransaction::runWithActor($actor, function (User $actor) use ($role, $name): Role {
            $target = Role::query()->lockForUpdate()->whereKey($role->getKey())->firstOrFail();

            if (! app(RolePolicy::class)->rename($actor, $target)) {
                abort(Response::HTTP_FORBIDDEN, ApiMessage::UNAUTHORIZED);
            }

            $duplicate = Role::query()
                ->where('name', $name)
                ->where('guard_name', $target->guard_name)
                ->where($target->getKeyName(), '<>', $target->getKey())
                ->exists();

            if ($duplicate) {
                $this->throwDuplicateNameError();
            }

            $target->name = $name;

            try {
                $target->save();
            } catch (UniqueConstraintViolationException) {
                $this->throwDuplicateNameError();
            }

            $this->auditRoleChange($actor, $target, Audit::ACTION_ROLE_RENAME);

            DB::afterCommit(static function (): void {
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            });

            return $target->refresh()->load('permissions');
        });
    }

    private function throwDuplicateNameError(): never
    {
        throw ValidationException::withMessages([
            'name' => [__('validation.unique', ['attribute' => 'nome'])],
        ]);
    }
}
