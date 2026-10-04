<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail('O campo :attribute deve ser uma string de 11 dígitos.');

            return;
        }

        $this->validateNormalizedDigits($attribute, $value, $fail);
    }

    private function validateNormalizedDigits(string $attribute, string $value, Closure $fail): void
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) !== 11) {
            $fail('O campo :attribute deve conter exatamente 11 dígitos.');

            return;
        }

        if (preg_match('/^(\d)\1{10}$/', $digits) === 1) {
            $fail('O campo :attribute não pode ter todos os dígitos repetidos.');

            return;
        }

        if (! $this->hasValidCheckDigits($digits)) {
            $fail('Os dígitos verificadores do campo :attribute são inválidos.');
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
