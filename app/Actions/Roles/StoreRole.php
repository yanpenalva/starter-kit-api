<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Actions\Concerns\RecordsPrivilegeAudit;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Support\ApiMessage;
use App\Support\Audit;
use App\Support\PrivilegeTransaction;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

final class StoreRole
{
    use RecordsPrivilegeAudit;

    public function run(User $actor, string $name): Role
    {
        return PrivilegeTransaction::runWithActor($actor, function (User $actor) use ($name): Role {
            if (! app(RolePolicy::class)->manage($actor)) {
                abort(Response::HTTP_FORBIDDEN, ApiMessage::UNAUTHORIZED);
            }

            $role = Role::query()->create(['name' => $name, 'guard_name' => 'web']);
            $this->auditRoleChange($actor, $role, Audit::ACTION_ROLE_STORE);

            return $role;
        });
    }
}
