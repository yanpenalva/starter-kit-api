<?php

declare(strict_types=1);

use App\Http\Controllers\Roles\RoleController;
use App\Http\Middleware\DisallowHeaderAuthentication;
use App\Http\Middleware\EnsureMfaChallenge;
use Illuminate\Support\Facades\Route;

Route::middleware([DisallowHeaderAuthentication::class, 'auth:sanctum', EnsureMfaChallenge::class])->group(static function (): void {
    Route::prefix('roles')->name('roles.')->group(static function (): void {
        Route::post('/', [RoleController::class, 'store']);
        Route::get('/', [RoleController::class, 'index']);
        Route::delete('/{role}', [RoleController::class, 'destroy']);
        Route::put('/{role}/permissions', [RoleController::class, 'updatePermissions']);
    });
});
