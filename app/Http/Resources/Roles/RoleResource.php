<?php

declare(strict_types=1);

namespace App\Http\Resources\Roles;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/**
 * @property-read Role $resource
 */
final class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->resource;

        /** @var list<string> $permissions */
        $permissions = $role->permissions->pluck('name')->sort()->values()->all();

        return [
            'id' => (int) $role->id,
            'name' => (string) $role->name,
            'guard_name' => (string) $role->guard_name,
            'permissions' => $permissions,
        ];
    }
}
