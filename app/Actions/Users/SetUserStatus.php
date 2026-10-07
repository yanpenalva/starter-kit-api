<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Concerns\RecordsPrivilegeAudit;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\ApiMessage;
use App\Support\PrivilegeTransaction;

final class SetUserStatus
{
    use RecordsPrivilegeAudit;

    public function run(User $actor, User $user, string $status): User
    {
        PrivilegeTransaction::run(function () use ($actor, $user, $status): void {
            /** @var User $target */
            $target = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! app(UserPolicy::class)->setStatus($actor, $target, $status)) {
                abort(403, ApiMessage::UNAUTHORIZED);
            }

            $target->update([
                'deactivated_at' => $status === 'deactivated' ? now() : null,
            ]);

            $this->auditUserChange($actor, $target);
        });

        return $user->refresh();
    }
}
