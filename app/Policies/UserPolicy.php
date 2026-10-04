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

    public function setStatus(User $actor, User $target): bool
    {
        if (! $this->canOperate($actor)) {
            return false;
        }

        if (! $actor->hasPermissionTo(Permission::UsersSetStatus->value)) {
            return false;
        }

        // An admin can never administratively deactivate themselves.
        return ! $actor->is($target);
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
