<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

if (app()->environment('testing', 'local')
    && is_file(base_path('storage/framework/testing/http-smoke-fixture.enabled'))) {
    Route::post('/testing/csrf-echo', static fn () => response()->noContent())
        ->middleware('web')
        ->name('testing.csrf-echo');
}
