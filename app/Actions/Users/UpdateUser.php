<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Concerns\RecordsPrivilegeAudit;
use App\Models\User;
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

            $target->fill($payload)->save();

            $this->auditUserChange($actor, $target);
        });

        return $user->refresh();
    }
}
