<?php

declare(strict_types=1);

namespace App\Http\Resources\Users;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read User $resource
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;
        $actor = $request->user();

        $canReadCpf = $actor instanceof User
            && app(UserPolicy::class)->readCpf($actor, $user);

        /** @var list<string> $roles */
        $roles = $user->getRoleNames()->sort()->values()->all();

        $verifiedAt = $user->email_verified_at !== null
            ? $user->email_verified_at->clone()->utc()->format('Y-m-d\TH:i:s\Z')
            : null;

        $deactivatedAt = $user->deactivated_at !== null
            ? $user->deactivated_at->clone()->utc()->format('Y-m-d\TH:i:s\Z')
            : null;

        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'email_verified_at' => $verifiedAt,
            'deactivated_at' => $deactivatedAt,
            'roles' => $roles,
            'cpf' => $this->when($canReadCpf, $user->cpf),
        ];
    }
}
