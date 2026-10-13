<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Policy foundation for the approved privilege mutations consumed by the
 * admin user/role endpoints in routes/api.php. Every actor must be verified
 * and active before any ability is considered — a granted permission alone
 * never satisfies an eligibility gate. Basic-profile public endpoints are
 * unaffected: these policies cover privileged admin operations only.
 */
final class UserPolicy
{
    public function manage(User $actor): bool
    {
        return $this->canOperate($actor)
            && $actor->hasPermissionTo(Permission::UsersManage->value);
    }

    public function delete(User $actor, User $target): bool
    {
        if (! $this->manage($actor) || $actor->is($target)) {
            return false;
        }

        if (! $target->hasRole(Role::Admin->value)) {
            return true;
        }

        $targetId = $target->getKey();

        return is_int($targetId) && User::otherAdminsRemain($targetId);
    }

    public function view(User $actor, User $target): bool
    {
        return $this->canOperate($actor)
            && $actor->hasPermissionTo(Permission::UsersView->value);
    }

    public function update(User $actor, User $target): bool
    {
        return $this->canOperate($actor)
            && $actor->hasPermissionTo(Permission::UsersUpdate->value);
    }

    public function updateCredentials(User $actor, User $target): bool
    {
        return ! $target->isPrivilegedAccount()
            || $actor->isPrivilegedOperator();
    }

    public function readCpf(User $actor, User $target): bool
    {
        return $this->canOperate($actor)
            && $actor->hasPermissionTo(Permission::UsersReadCpf->value);
    }

    /**
     * The requested transition: 'active' or 'deactivated'. An admin can never
     * administratively deactivate themselves, and deactivating an admin must
     * always leave at least one active admin standing. Reactivation only
     * requires an eligible actor.
     */
    public function setStatus(User $actor, User $target, string $status): bool
    {
        if (! $this->canOperate($actor)) {
            return false;
        }

        if (! $actor->hasPermissionTo(Permission::UsersSetStatus->value)) {
            return false;
        }

        if ($actor->is($target)) {
            return false;
        }

        if ($status === 'deactivated'
            && $target->deactivated_at === null
            && $target->hasRole(Role::Admin->value)) {
            $targetId = $target->getKey();

            if (! is_int($targetId) || ! User::otherAdminsRemain($targetId)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Assigning (or changing) roles denies every self-assignment, preserves
     * the last standing admin and requires an eligible verified actor.
     * When target roles are specified, non-admin actors cannot assign roles
     * whose permissions exceed the actor's own permissions (grant ceiling).
     *
     * @param  list<string>  $roleNames
     */
    public function assignRoles(User $actor, User $target, array $roleNames = []): bool
    {
        if (! $this->canOperate($actor)) {
            return false;
        }

        if (! $actor->hasPermissionTo(Permission::UsersAssignRoles->value)) {
            return false;
        }

        if ($actor->is($target)) {
            return false;
        }

        if ($target->hasRole(Role::Admin->value)) {
            $targetId = $target->getKey();

            if (! is_int($targetId) || ! User::otherAdminsRemain($targetId)) {
                return false;
            }
        }

        if ($actor->hasRole(Role::Admin->value)) {
            return true;
        }

        if ($roleNames === []) {
            return true;
        }

        if (in_array(Role::Admin->value, $roleNames, true)) {
            return false;
        }

        $actorPermissions = $actor->getAllPermissions()->pluck('name')->all();

        $targetRoles = SpatieRole::query()
            ->whereIn('name', $roleNames)
            ->where('guard_name', 'web')
            ->with('permissions')
            ->get();

        if ($targetRoles->count() !== count(array_unique($roleNames))) {
            return false;
        }

        foreach ($targetRoles as $role) {
            foreach ($role->permissions as $permission) {
                if (! in_array($permission->name, $actorPermissions, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * A privileged actor must be verified and not deactivated, whatever
     * permissions or roles its account carries.
     */
    private function canOperate(User $actor): bool
    {
        return $actor->email_verified_at !== null && $actor->deactivated_at === null;
    }
}
