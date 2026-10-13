<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class Correlation
{
    public const HEADER = 'X-Correlation-Id';

    private static ?string $current = null;

    public static function fromRequest(?string $headerValue): string
    {
        if (is_string($headerValue) && Str::isUuid($headerValue)) {
            return $headerValue;
        }

        return self::generate();
    }

    public static function generate(): string
    {
        return (string) Str::uuid();
    }

    public static function use(string $correlationId): void
    {
        self::$current = Str::isUuid($correlationId) ? $correlationId : self::generate();
        Log::withContext(['correlation_id' => self::$current]);
    }

    public static function current(): string
    {
        if (self::$current === null) {
            self::$current = self::generate();
            Log::withContext(['correlation_id' => self::$current]);
        }

        return self::$current;
    }
}
