<?php

declare(strict_types=1);

namespace Tests\Concern;

use LogicException;

trait UsesTypedDatabaseConfig
{
    private function databaseConnectionName(): string
    {
        $connection = config('database.default');

        if (! is_string($connection)) {
            throw new LogicException('The default database connection must be a string.');
        }

        return $connection;
    }

    /** @return array<string, mixed> */
    private function databaseConnectionConfig(string $connection): array
    {
        $configuration = config("database.connections.{$connection}");

        if (! is_array($configuration)) {
            throw new LogicException("Database connection [{$connection}] must have an array configuration.");
        }

        $typedConfiguration = [];
        foreach ($configuration as $key => $value) {
            if (! is_string($key)) {
                throw new LogicException("Database connection [{$connection}] must use string configuration keys.");
            }

            $typedConfiguration[$key] = $value;
        }

        return $typedConfiguration;
    }

    /** @param array<string, mixed> $configuration */
    private function databaseConfigString(array $configuration, string $key, string $default = ''): string
    {
        $value = $configuration[$key] ?? null;

        return match (true) {
            is_string($value) => $value,
            is_int($value) => (string) $value,
            $value === null => $default,
            default => throw new LogicException("Database configuration [{$key}] must be a string or integer."),
        };
    }
}
