<?php

declare(strict_types=1);

namespace App\Http\Resources\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read User $resource
 */
final class MyProfileResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     email: string,
     *     email_verified_at: string|null,
     *     roles: list<string>,
     *     role_display_names: array<string, string>,
     *     permissions: list<string>
     * }
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        $isVerified = $user->email_verified_at !== null;

        /** @var list<string> $roles */
        $roles = $isVerified
            ? $user->getRoleNames()->sort()->values()->all()
            : [];
        /** @var array<string, string> $roleDisplayNames */
        $roleDisplayNames = array_combine(
            $roles,
            array_map(Role::displayNameFor(...), $roles),
        );

        /** @var list<string> $permissions */
        $permissions = $isVerified
            ? $user->getAllPermissions()->pluck('name')->sort()->values()->all()
            : [];

        $verifiedAt = $user->email_verified_at !== null
            ? $user->email_verified_at->clone()->utc()->format('Y-m-d\TH:i:s\Z')
            : null;

        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'email_verified_at' => $verifiedAt,
            'roles' => $roles,
            'role_display_names' => $roleDisplayNames,
            'permissions' => $permissions,
        ];
    }
}
