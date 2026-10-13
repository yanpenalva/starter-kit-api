<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Grant ceiling: a non-admin actor may only assign roles whose permissions are
 * all held by the actor (directly or through the actor's roles). The role list
 * is evaluated as a whole — if any requested role exceeds the ceiling (or is
 * the admin role), nothing is assigned. Policy denials, including the ceiling,
 * respond 403 Forbidden with the unauthorized message; unknown role names are
 * rejected earlier by validation with 422.
 */
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
            'roles.*' => ['string', 'distinct', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
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
