<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Correlation ids come from the X-Correlation-Id header only when they are
 * valid UUIDs; anything else (log forgery, raw PII, garbage) is replaced by a
 * freshly generated UUID. No trust widening and no raw values are logged.
 */
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
