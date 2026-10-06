<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Rules\Cpf;
use App\Support\EmailLookup;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => EmailLookup::normalize($email)]);
        }

        $cpf = $this->input('cpf');

        if (is_string($cpf)) {
            $digits = preg_replace('/\D/', '', $cpf) ?? '';
            $this->merge(['cpf' => $digits === '' ? null : $digits]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (EmailLookup::exists(EmailLookup::normalize($value))) {
                        $fail((string) __('validation.unique', ['attribute' => $attribute]));
                    }
                },
            ],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'cpf' => ['nullable', 'string', new Cpf, Rule::unique('users', 'cpf')],
        ];
    }

    public function normalizedEmail(): string
    {
        return EmailLookup::normalize($this->input('email'));
    }
}
