<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Concerns\RecordsPrivilegeAudit;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\ApiMessage;
use App\Support\PrivilegeTransaction;

final class UpdateUser
{
    use RecordsPrivilegeAudit;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function run(User $actor, User $user, array $payload): User
    {
        PrivilegeTransaction::run(function () use ($actor, $user, $payload): void {
            /** @var User $target */
            $target = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! app(UserPolicy::class)->update($actor, $target)) {
                abort(403, ApiMessage::UNAUTHORIZED);
            }

            $target->fill($payload)->save();

            $this->auditUserChange($actor, $target);
        });

        return $user->refresh();
    }
}
