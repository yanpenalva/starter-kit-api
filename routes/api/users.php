<?php

declare(strict_types=1);

use App\Http\Controllers\Users\UserController;
use App\Http\Middleware\DisallowHeaderAuthentication;
use Illuminate\Support\Facades\Route;

Route::middleware([DisallowHeaderAuthentication::class, 'auth:sanctum'])->group(static function (): void {
    Route::prefix('users')->name('users.')->group(static function (): void {
        Route::post('/', [UserController::class, 'store']);
        Route::get('/', [UserController::class, 'index']);
        Route::delete('/{user}', [UserController::class, 'destroy']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::patch('/{user}', [UserController::class, 'update']);
        Route::post('/{user}/status', [UserController::class, 'setStatus']);
        Route::put('/{user}/roles', [UserController::class, 'assignRoles']);
    });
});
