<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Concerns\RecordsPrivilegeAudit;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\ApiMessage;
use App\Support\PrivilegeTransaction;

final class StoreUser
{
    use RecordsPrivilegeAudit;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function run(User $actor, array $payload): User
    {
        return PrivilegeTransaction::run(function () use ($actor, $payload): User {
            if (! app(UserPolicy::class)->manage($actor)) {
                abort(403, ApiMessage::UNAUTHORIZED);
            }

            $active = $payload['active'] ?? null;
            assert(is_bool($active));

            $user = User::query()->create([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'cpf' => $payload['cpf'],
                'password' => $payload['password'],
                'deactivated_at' => $active ? null : now(),
            ]);

            $this->auditUserChange($actor, $user);

            return $user;
        });
    }
}
