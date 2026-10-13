<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class AssignUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof User && $actor->can(Permission::UsersAssignRoles->value);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
        ] + RoleNameRules::validationRules();
    }

    /**
     * @return list<string>
     */
    public function roleNames(): array
    {
        $roles = $this->validated('roles', []);

        if (! is_array($roles)) {
            return [];
        }

        $names = [];

        foreach ($roles as $role) {
            if (is_string($role)) {
                $names[] = $role;
            }
        }

        return $names;
    }
}
