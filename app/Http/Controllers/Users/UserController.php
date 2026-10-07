<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Actions\Users\AssignUserRoles;
use App\Actions\Users\DestroyUser;
use App\Actions\Users\IndexUsers;
use App\Actions\Users\SetUserStatus;
use App\Actions\Users\ShowUser;
use App\Actions\Users\StoreUser;
use App\Actions\Users\UpdateUser;
use App\Http\Requests\Users\AssignUserRolesRequest;
use App\Http\Requests\Users\IndexUsersRequest;
use App\Http\Requests\Users\SetUserStatusRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class UserController
{
    use AuthorizesRequests;

    public function __construct(
        private IndexUsers $indexUsers,
        private StoreUser $storeUser,
        private ShowUser $showUser,
        private UpdateUser $updateUser,
        private DestroyUser $destroyUser,
        private SetUserStatus $setUserStatus,
        private AssignUserRoles $assignUserRoles,
    ) {}

    /**
     * @return AnonymousResourceCollection<int, UserResource>
     */
    public function index(IndexUsersRequest $request): AnonymousResourceCollection
    {
        $actor = $request->user();
        assert($actor instanceof User);

        $users = $this->indexUsers->run($actor, $request->filters(), $request->perPage(), $request->page());

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $actor = $request->user();
        assert($actor instanceof User);

        /** @var array<string, mixed> $payload */
        $payload = $request->validated();

        $user = $this->storeUser->run($actor, $payload);

        return (new UserResource($user))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();
        assert($actor instanceof User);

        $this->authorize('delete', $user);

        $this->destroyUser->run($actor, $user);

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    public function show(Request $request, User $user): UserResource
    {
        $actor = $request->user();
        assert($actor instanceof User);

        $this->authorize('view', $user);

        return new UserResource($this->showUser->run($actor, $user));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $actor = $request->user();
        assert($actor instanceof User);

        /** @var array<string, mixed> $payload */
        $payload = $request->validated();

        $this->authorize('update', $user);

        return new UserResource($this->updateUser->run($actor, $user, $payload));
    }

    public function setStatus(SetUserStatusRequest $request, User $user): UserResource
    {
        $actor = $request->user();
        assert($actor instanceof User);

        $this->authorize('setStatus', [$user, $request->status()]);

        return new UserResource($this->setUserStatus->run($actor, $user, $request->status()));
    }

    public function assignRoles(AssignUserRolesRequest $request, User $user): UserResource
    {
        $actor = $request->user();
        assert($actor instanceof User);

        $this->authorize('assignRoles', $user);

        return new UserResource($this->assignUserRoles->run($actor, $user, $request->roleNames()));
    }
}
