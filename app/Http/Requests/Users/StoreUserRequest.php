<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Enums\Permission;
use App\Models\User;
use App\Support\EmailLookup;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof User && $actor->can(Permission::UsersManage->value);
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => EmailLookup::normalize($email)]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:254',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (EmailLookup::exists(EmailLookup::normalize($value))) {
                        $fail((string) __('validation.unique', ['attribute' => $attribute]));
                    }
                },
            ],
            'cpf' => ['required', 'string', 'regex:/^\d{11}$/', Rule::unique('users', 'cpf')],
            'password' => ['required', 'string', 'min:12', 'max:255'],
            'ativo' => ['required', 'boolean'],
        ];
    }
}
