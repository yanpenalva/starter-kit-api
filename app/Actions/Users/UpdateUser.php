<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Concerns\RecordsPrivilegeAudit;
use App\Models\User;
use App\Support\PrivilegeTransaction;

final class UpdateUser
{
    use RecordsPrivilegeAudit;

    private const CREDENTIAL_FIELDS = ['email', 'password', 'remember_token'];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function run(User $actor, User $user, array $payload): User
    {
        $changesCredentials = array_intersect(self::CREDENTIAL_FIELDS, array_keys($payload)) !== [];

        if ($changesCredentials && $user->isPrivilegedAccount() && ! $actor->isPrivilegedOperator()) {
            abort(403);
        }

        PrivilegeTransaction::run(function () use ($actor, $user, $payload, $changesCredentials): void {
            /** @var User $target */
            $target = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($changesCredentials && $target->isPrivilegedAccount() && ! $actor->isPrivilegedOperator()) {
                abort(403);
            }

            $target->fill($payload)->save();

            $this->auditUserChange($actor, $target);
        });

        return $user->refresh();
    }
}
