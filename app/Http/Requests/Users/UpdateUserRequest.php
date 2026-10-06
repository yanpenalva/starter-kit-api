<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Enums\Permission;
use App\Http\Requests\HasRules;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Wave B contract: update basic account attributes. The wave owning the
 * route enforces UserPolicy::update for the resolved target inside a
 * PrivilegeTransaction; this request carries only the actor ability gate
 * and the input shape.
 */
final class UpdateUserRequest extends FormRequest implements HasRules
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof User && $actor->can(Permission::UsersUpdate->value);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:254'],
            'cpf' => ['sometimes', 'nullable', 'string', 'regex:/^\d{11}$/'],
        ];
    }
}
