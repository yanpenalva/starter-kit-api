<?php

declare(strict_types=1);

namespace Tests\Concern;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Authenticates HTTP tests against the sanctum guard directly. The app is a
 * session-only SPA (Authorization headers are rejected, no API tokens), so
 * Sanctum::actingAs() does not apply: the user model carries no API token
 * machinery. Setting the guard user explicitly also defeats the guard
 * singleton memoization that leaks the previous actor into the next request
 * inside a single test process.
 */
trait ActsAsSanctumUser
{
    private static function actingAsSanctum(User $actor): void
    {
        Auth::guard('sanctum')->setUser($actor);
        Auth::shouldUse('sanctum');
    }
}
