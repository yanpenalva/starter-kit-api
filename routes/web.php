<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Sanctum auto-registers GET /sanctum/csrf-cookie (named sanctum.csrf-cookie);
// this file owns only the test-only HTTP smoke fixture. It is gated by BOTH a
// non-production environment (testing|local) AND the flag file: production and
// staging never register the route even if a flag file leaks. The production
// surface stays GET /api/v1, GET /up and GET /sanctum/csrf-cookie only.
if (app()->environment('testing', 'local')
    && is_file(base_path('storage/framework/testing/http-smoke-fixture.enabled'))) {
    Route::post('/testing/csrf-echo', static fn () => response()->noContent())
        ->middleware('web')
        ->name('testing.csrf-echo');
}
