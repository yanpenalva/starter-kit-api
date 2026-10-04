<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Rules\Cpf;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'deactivated_at' => null,
            'cpf' => null,
            'password' => 'password',
            'remember_token' => Str::random(10),
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => now()]);
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }

    public function deactivated(): static
    {
        return $this->state(fn (): array => ['deactivated_at' => now()]);
    }

    public function withCpf(?string $cpf = null): static
    {
        return $this->state(function () use ($cpf): array {
            if (is_string($cpf)) {
                // Normalize before the state resolves; leading zeros survive
                // because the value stays a string. Garbage normalizes to an
                // empty string and fails validation below, never generating.
                $cpf = preg_replace('/\D/', '', $cpf) ?? '';
            }

            $value = $cpf ?? self::generateCpf();

            $validated = Validator::make(['cpf' => $value], ['cpf' => ['string', new Cpf]]);

            if ($validated->fails()) {
                throw new RuntimeException('Generated CPF is invalid.');
            }

            return ['cpf' => $value];
        });
    }

    public static function generateCpf(): string
    {
        $base = '';

        for ($index = 0; $index < 9; $index++) {
            $base .= (string) random_int(0, 9);
        }

        return $base.self::checkDigit($base, 10).self::checkDigit($base.self::checkDigit($base, 10), 11);
    }

    private static function checkDigit(string $digits, int $weightStart): string
    {
        $sum = 0;

        for ($index = 0; $index < strlen($digits); $index++) {
            $sum += (int) $digits[$index] * ($weightStart - $index);
        }

        $remainder = $sum % 11;

        return (string) ($remainder < 2 ? 0 : 11 - $remainder);
    }
}
