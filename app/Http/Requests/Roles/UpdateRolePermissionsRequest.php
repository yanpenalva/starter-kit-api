<?php

declare(strict_types=1);

namespace App\Http\Requests\Roles;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof User && $actor->can(Permission::RolesUpdatePermissions->value);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(Permission::values())],
        ];
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        $permissions = $this->validated('permissions', []);

        if (! is_array($permissions)) {
            return [];
        }

        $names = [];

        foreach ($permissions as $permission) {
            if (is_string($permission)) {
                $names[] = $permission;
            }
        }

        return $names;
    }
}
