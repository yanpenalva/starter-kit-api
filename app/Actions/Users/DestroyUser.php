<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Concerns\RecordsPrivilegeAudit;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\ApiMessage;
use App\Support\PrivilegeTransaction;
use Symfony\Component\HttpFoundation\Response;

final class DestroyUser
{
    use RecordsPrivilegeAudit;

    public function run(User $actor, User $user): void
    {
        PrivilegeTransaction::run(function () use ($actor, $user): void {
            $target = User::query()->lockForUpdate()->whereKey($user->getKey())->firstOrFail();

            if (! app(UserPolicy::class)->delete($actor, $target)) {
                abort(Response::HTTP_FORBIDDEN, ApiMessage::UNAUTHORIZED);
            }

            $this->auditUserChange($actor, $target);
            $target->delete();
        });
    }
}
