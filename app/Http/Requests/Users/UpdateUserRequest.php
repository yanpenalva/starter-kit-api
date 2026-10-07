<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Enums\Permission;
use App\Models\User;
use App\Support\EmailLookup;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Wave B contract: update basic account attributes. The wave owning the
 * route enforces UserPolicy::update for the resolved target inside a
 * PrivilegeTransaction; this request carries only the actor ability gate
 * and the input shape.
 */
final class UpdateUserRequest extends FormRequest
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
        $targetKey = $this->route('user') instanceof User
            ? $this->route('user')->getKey()
            : $this->route('user');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:254',
                static function (string $attribute, mixed $value, Closure $fail) use ($targetKey): void {
                    $conflict = EmailLookup::find(EmailLookup::normalize($value));

                    if ($conflict instanceof User && $conflict->getKey() != $targetKey) {
                        $fail((string) __('validation.unique', ['attribute' => $attribute]));
                    }
                },
            ],
            'cpf' => ['sometimes', 'nullable', 'string', 'regex:/^\\d{11}$/', Rule::unique('users', 'cpf')->ignore($targetKey)],
        ];
    }
}
