<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Role;
use App\Models\User;
use App\Support\Audit;
use App\Support\Correlation;
use App\Support\PrivilegeTransaction;
use DomainException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

final class BootstrapAdmin
{
    /**
     * Idempotent, transactional, audit-fail-closed promotion of an explicitly
     * chosen verified active account (by ID) into the admin role.
     *
     * @return array{user: User, already_admin: bool, target_id: int}
     */
    public function run(int $userId, bool $confirmedForProduction): array
    {
        if (app()->environment('production') && ! $confirmedForProduction) {
            throw new DomainException('O bootstrap em produção exige a flag de confirmação explícita.');
        }

        return PrivilegeTransaction::run(function () use ($userId): array {
            $user = User::query()->lockForUpdate()->find($userId);

            if ($user === null) {
                throw new RuntimeException('O alvo do bootstrap não existe.');
            }

            if ($user->email_verified_at === null) {
                throw new RuntimeException('O alvo do bootstrap não está verificado.');
            }

            if ($user->deactivated_at !== null) {
                throw new RuntimeException('O alvo do bootstrap está desativado.');
            }

            $key = $user->getKey();
            assert(is_int($key));

            if ($user->hasRole(Role::Admin->value)) {
                return ['user' => $user, 'already_admin' => true, 'target_id' => $key];
            }

            $user->assignRole(Role::Admin->value);

            Audit::record(
                Audit::EVENT_CLI_BOOTSTRAP,
                'user',
                $key,
                Correlation::current(),
                ['actor_type' => 'cli', 'actor_id' => null],
                'Admin role granted by restricted CLI',
            );

            DB::afterCommit(function () use ($user): void {
                app(PermissionRegistrar::class)->forgetCachedPermissions();
                $user->unsetRelation('roles');
            });

            return ['user' => $user, 'already_admin' => false, 'target_id' => $key];
        });
    }
}
