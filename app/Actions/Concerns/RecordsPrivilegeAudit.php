<?php

declare(strict_types=1);

namespace App\Actions\Concerns;

use App\Models\User;
use App\Support\Audit;
use App\Support\Correlation;
use Spatie\Permission\Models\Role;

trait RecordsPrivilegeAudit
{
    private function auditUserChange(User $actor, User $target): void
    {
        $targetKey = $target->getKey();
        assert(is_int($targetKey));
        $actorKey = $actor->getKey();
        assert(is_int($actorKey));

        Audit::record(
            Audit::EVENT_PRIVILEGED_CHANGE,
            'user',
            $targetKey,
            Correlation::current(),
            ['actor_type' => 'user', 'actor_id' => $actorKey],
        );
    }

    private function auditRoleChange(User $actor, Role $role): void
    {
        $roleKey = $role->getKey();
        assert(is_int($roleKey));
        $actorKey = $actor->getKey();
        assert(is_int($actorKey));

        Audit::record(
            Audit::EVENT_PRIVILEGED_CHANGE,
            'role',
            $roleKey,
            Correlation::current(),
            ['actor_type' => 'user', 'actor_id' => $actorKey],
        );
    }
}
