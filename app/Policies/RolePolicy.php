<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Spatie\Permission\Models\Role as SpatieRole;

final class RolePolicy
{
    public function manage(User $actor): bool
    {
        return $this->canOperate($actor)
            && $actor->hasPermissionTo(Permission::RolesManage->value);
    }

    public function delete(User $actor, SpatieRole $role): bool
    {
        return $this->manage($actor);
    }

    public function view(User $actor): bool
    {
        return $this->canOperate($actor)
            && $actor->hasPermissionTo(Permission::RolesView->value);
    }

    /**
     * The admin role itself is immutable: its permission set can never be
     * rewritten through this mutation.
     */
    /**
     * @param  list<string>  $permissionNames
     */
    public function updatePermissions(User $actor, SpatieRole $role, array $permissionNames): bool
    {
        if (! $this->canOperate($actor)) {
            return false;
        }

        if (! $actor->hasPermissionTo(Permission::RolesUpdatePermissions->value)) {
            return false;
        }

        if ($role->name === Role::Admin->value) {
            return false;
        }

        if ($actor->hasRole(Role::Admin->value)) {
            return true;
        }

        foreach ($permissionNames as $permissionName) {
            if (! $actor->getAllPermissions()->contains('name', $permissionName)) {
                return false;
            }
        }

        return true;
    }

    private function canOperate(User $actor): bool
    {
        return $actor->email_verified_at !== null && $actor->deactivated_at === null;
    }
}
