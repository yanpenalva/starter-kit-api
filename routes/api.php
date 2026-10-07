<?php

declare(strict_types=1);

use App\Http\Controllers\Audit\ActivityLogController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MyProfileController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\Users\UserController;
use App\Http\Middleware\DisallowHeaderAuthentication;
use App\Http\Middleware\EnsureAccountNotDeactivated;
use App\Http\Middleware\EnsureStatefulSession;
use Illuminate\Support\Facades\Route;

Route::get('/', static fn (): array => ['status' => 'ok']);
Route::get('/health', HealthController::class);

Route::post('/login', LoginController::class)
    ->middleware([DisallowHeaderAuthentication::class, 'throttle:login', EnsureStatefulSession::class]);

Route::post('/register', RegisterController::class)
    ->middleware([DisallowHeaderAuthentication::class, 'throttle:register', EnsureStatefulSession::class]);

Route::post('/forgot-password', ForgotPasswordController::class)
    ->middleware([DisallowHeaderAuthentication::class, 'throttle:forgot'])
    ->name('password.email');

Route::post('/reset-password', ResetPasswordController::class)
    ->middleware([DisallowHeaderAuthentication::class, 'throttle:reset'])
    ->name('password.update');

Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
    ->middleware([DisallowHeaderAuthentication::class, 'signed', 'throttle:verification'])
    ->name('verification.verify');

Route::post('/email/verification-notification', EmailVerificationNotificationController::class)
    ->middleware([DisallowHeaderAuthentication::class, 'auth:sanctum', 'throttle:verification'])
    ->name('verification.send');

Route::middleware([DisallowHeaderAuthentication::class, 'auth:sanctum'])->group(static function (): void {
    Route::get('/auth/my-profile', MyProfileController::class)
        ->middleware(EnsureAccountNotDeactivated::class);

    Route::post('/logout', LogoutController::class);

    Route::prefix('users')->name('users.')->group(static function (): void {
        Route::post('/', [UserController::class, 'store']);
        Route::get('/', [UserController::class, 'index']);
        Route::delete('/{user}', [UserController::class, 'destroy']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::patch('/{user}', [UserController::class, 'update']);
        Route::post('/{user}/status', [UserController::class, 'setStatus']);
        Route::put('/{user}/roles', [UserController::class, 'assignRoles']);
    });

    Route::prefix('roles')->name('roles.')->group(static function (): void {
        Route::post('/', [RoleController::class, 'store']);
        Route::get('/', [RoleController::class, 'index']);
        Route::delete('/{role}', [RoleController::class, 'destroy']);
        Route::put('/{role}/permissions', [RoleController::class, 'updatePermissions']);
    });

    Route::get('/activity-log', [ActivityLogController::class, 'index'])
        ->middleware('can:logs.view')
        ->name('activity-log.index');
});
