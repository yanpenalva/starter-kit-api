<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\EmailLookup;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateMyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => EmailLookup::normalize($email)]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->user();
        $userId = $user instanceof User ? $user->getKey() : null;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:254',
                static function (string $attribute, mixed $value, Closure $fail) use ($userId): void {
                    if (! is_string($value)) {
                        return;
                    }

                    $conflict = EmailLookup::find(EmailLookup::normalize($value));

                    if ($conflict instanceof User && $conflict->getKey() !== $userId) {
                        $fail((string) __('validation.unique', ['attribute' => $attribute]));
                    }
                },
            ],
            'current_password' => ['required_with:password', 'current_password'],
            'password' => ['sometimes', 'string', 'min:12', 'max:255', 'confirmed'],
        ];
    }
}
