<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Enums\Permission;
use App\Models\User;
use App\Http\Requests\HasRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Wave B contract: activate or deactivate an account. The wave owning the
 * route enforces UserPolicy::setStatus (self and last-admin protections)
 * for the resolved target inside a PrivilegeTransaction.
 */
final class SetUserStatusRequest extends FormRequest implements HasRules
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof User && $actor->can(Permission::UsersSetStatus->value);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['active', 'deactivated'])],
        ];
    }

    public function status(): string
    {
        $status = $this->validated('status');

        return is_string($status) ? $status : '';
    }
}
