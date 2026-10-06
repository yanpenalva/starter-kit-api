<?php

declare(strict_types=1);

use App\Http\Controllers\Audit\ActivityLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MyProfileController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\Users\UserController;
use App\Http\Middleware\DisallowHeaderAuthentication;
use App\Http\Middleware\EnsureAccountNotDeactivated;
use App\Http\Middleware\EnsureStatefulSession;
use Illuminate\Support\Facades\Route;

Route::get('/', static fn (): array => ['status' => 'ok']);

Route::post('/login', LoginController::class)
    ->middleware([DisallowHeaderAuthentication::class, 'throttle:login', EnsureStatefulSession::class]);

Route::middleware([DisallowHeaderAuthentication::class, 'auth:sanctum'])->group(static function (): void {
    Route::get('/auth/my-profile', MyProfileController::class)
        ->middleware(EnsureAccountNotDeactivated::class);

    Route::post('/logout', LogoutController::class);

    Route::prefix('users')->name('users.')->group(static function (): void {
        Route::get('/', [UserController::class, 'index'])->middleware('can:users.view');
        Route::get('/{user}', [UserController::class, 'show'])->middleware('can:users.view');
        Route::patch('/{user}', [UserController::class, 'update'])->middleware('can:users.update');
        Route::post('/{user}/status', [UserController::class, 'setStatus'])->middleware('can:users.set-status');
        Route::put('/{user}/roles', [UserController::class, 'assignRoles'])->middleware('can:users.assign-roles');
    });

    Route::prefix('roles')->name('roles.')->group(static function (): void {
        Route::get('/', [RoleController::class, 'index'])->middleware('can:roles.view');
        Route::put('/{role}/permissions', [RoleController::class, 'updatePermissions'])->middleware('can:roles.update-permissions');
    });

    Route::get('/activity-log', [ActivityLogController::class, 'index'])
        ->middleware('can:logs.view')
        ->name('activity-log.index');
});
