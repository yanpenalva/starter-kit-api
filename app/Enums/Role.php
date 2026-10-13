<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case User = 'user';

    public static function displayNameFor(string $name): string
    {
        return match ($name) {
            self::Admin->value => 'Administrador',
            self::User->value => 'Usuário',
            default => $name,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
