<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;

/**
 * Policy foundation for the approved privilege mutations. No public endpoint
 * consumes these yet; the wave owning each route wires them in. Every actor
 * must be verified and active before any ability is considered — a granted
 * permission alone never satisfies an eligibility gate. Basic-profile public
 * endpoints (future wave) are unaffected: these policies cover privileged
 * admin operations only.
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
     */
    public function assignRoles(User $actor, User $target): bool
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
