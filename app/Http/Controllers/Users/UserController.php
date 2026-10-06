<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Http\Requests\Users\AssignUserRolesRequest;
use App\Http\Requests\Users\IndexUsersRequest;
use App\Http\Requests\Users\SetUserStatusRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\Users\UserResource;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\ApiMessage;
use App\Support\Audit;
use App\Support\Correlation;
use App\Support\Filters\UserFilters;
use App\Support\PrivilegeTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Wave B: administrative account routes. Every mutation re-resolves the
 * target under lock, consults UserPolicy inside PrivilegeTransaction and
 * writes a fail-closed audit record in the same PostgreSQL transaction.
 */
final class UserController
{
    /**
     * @return AnonymousResourceCollection<int, UserResource>
     */
    public function index(IndexUsersRequest $request): AnonymousResourceCollection
    {
        $users = UserFilters::apply(User::query(), $request->filters())
            ->orderBy('id')
            ->paginate($request->perPage(), ['*'], 'page', $request->page());

        return UserResource::collection($users);
    }

    public function show(Request $request, User $user): UserResource
    {
        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $actor = $request->user();
        assert($actor instanceof User);

        PrivilegeTransaction::run(function () use ($actor, $user, $request): void {
            /** @var User $target */
            $target = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! app(UserPolicy::class)->update($actor, $target)) {
                abort(403, ApiMessage::UNAUTHORIZED);
            }

            /** @var array<string, mixed> $payload */
            $payload = $request->validated();
            $target->fill($payload)->save();

            $this->auditUserChange($actor, $target);
        });

        return new UserResource($user->refresh());
    }

    public function setStatus(SetUserStatusRequest $request, User $user): UserResource
    {
        $actor = $request->user();
        assert($actor instanceof User);
        $status = $request->status();

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

        return new UserResource($user->refresh());
    }

    public function assignRoles(AssignUserRolesRequest $request, User $user): UserResource
    {
        $actor = $request->user();
        assert($actor instanceof User);
        $roleNames = $request->roleNames();

        PrivilegeTransaction::run(function () use ($actor, $user, $roleNames): void {
            /** @var User $target */
            $target = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! app(UserPolicy::class)->assignRoles($actor, $target)) {
                abort(403, ApiMessage::UNAUTHORIZED);
            }

            $target->syncRoles($roleNames);

            $this->auditUserChange($actor, $target);

            DB::afterCommit(static function () use ($target): void {
                app(PermissionRegistrar::class)->forgetCachedPermissions();
                $target->unsetRelation('roles');
            });
        });

        return new UserResource($user->refresh());
    }

    private function auditUserChange(User $actor, User $target): void
    {
        $targetKey = $target->getKey();
        assert(is_int($targetKey));
        $actorKey = $actor->getKey();
        assert(is_int($actorKey));

        Audit::record(
            Audit::EVENT_PRIVILEGED_CHANGE,
            'user',
            $targetKey,
            Correlation::current(),
            ['actor_type' => 'user', 'actor_id' => $actorKey],
        );
    }
}
