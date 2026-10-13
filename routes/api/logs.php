<?php

declare(strict_types=1);

use App\Http\Controllers\Audit\ActivityLogController;
use App\Http\Middleware\DisallowHeaderAuthentication;
use App\Http\Middleware\EnsureMfaChallenge;
use Illuminate\Support\Facades\Route;

Route::middleware([DisallowHeaderAuthentication::class, 'auth:sanctum', EnsureMfaChallenge::class])->group(static function (): void {
    Route::get('/activity-log', [ActivityLogController::class, 'index'])
        ->middleware('can:logs.view')
        ->name('activity-log.index');
});
