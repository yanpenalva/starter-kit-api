<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Actions\Concerns\RecordsPrivilegeAudit;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Support\ApiMessage;
use App\Support\PrivilegeTransaction;
use Spatie\Permission\Models\Role;

final class StoreRole
{
    use RecordsPrivilegeAudit;

    public function run(User $actor, string $name): Role
    {
        return PrivilegeTransaction::run(function () use ($actor, $name): Role {
            if (! app(RolePolicy::class)->manage($actor)) {
                abort(403, ApiMessage::UNAUTHORIZED);
            }

            $role = Role::query()->create(['name' => $name, 'guard_name' => 'web']);
            $this->auditRoleChange($actor, $role);

            return $role;
        });
    }
}
