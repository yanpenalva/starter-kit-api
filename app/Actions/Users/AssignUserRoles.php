<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Concerns\RecordsPrivilegeAudit;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\ApiMessage;
use App\Support\PrivilegeTransaction;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

final class AssignUserRoles
{
    use RecordsPrivilegeAudit;

    /**
     * @param  list<string>  $roleNames
     */
    public function run(User $actor, User $user, array $roleNames): User
    {
        PrivilegeTransaction::runWithActor($actor, function (User $actor) use ($user, $roleNames): void {
            /** @var User $target */
            $target = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! app(UserPolicy::class)->assignRoles($actor, $target, $roleNames)) {
                abort(Response::HTTP_FORBIDDEN, ApiMessage::UNAUTHORIZED);
            }

            $target->syncRoles($roleNames);

            $this->auditUserChange($actor, $target);

            DB::afterCommit(static function () use ($target): void {
                app(PermissionRegistrar::class)->forgetCachedPermissions();
                $target->unsetRelation('roles');
            });
        });

        return $user->refresh();
    }
}
