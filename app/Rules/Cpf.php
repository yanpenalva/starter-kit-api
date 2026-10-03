<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates an optional CPF provided as a normalized 11-digit string.
 * Leading zeros are preserved by storing the value as a string; the
 * caller normalizes (digits only) before or after validation.
 */
final class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail('The :attribute must be a string of 11 digits.');

            return;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) !== 11) {
            $fail('The :attribute must contain exactly 11 digits.');

            return;
        }

        if (preg_match('/^(\d)\1{10}$/', $digits) === 1) {
            $fail('The :attribute must not have all repeated digits.');

            return;
        }

        if (! $this->hasValidCheckDigits($digits)) {
            $fail('The :attribute check digits are invalid.');
        }
    }

    private function hasValidCheckDigits(string $digits): bool
    {
        return $this->checkDigit($digits, 9, 10) === (int) $digits[9]
            && $this->checkDigit($digits, 10, 11) === (int) $digits[10];
    }

    private function checkDigit(string $digits, int $length, int $weightStart): int
    {
        $sum = 0;

        for ($index = 0; $index < $length; $index++) {
            $sum += (int) $digits[$index] * ($weightStart - $index);
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
