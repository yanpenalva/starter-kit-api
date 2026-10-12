<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MyProfileController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\UpdateMyProfileController;
use App\Http\Controllers\Auth\UpdatePasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Middleware\DisallowHeaderAuthentication;
use App\Http\Middleware\EnsureAccountNotDeactivated;
use App\Http\Middleware\EnsureStatefulSession;
use Illuminate\Support\Facades\Route;

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
    ->middleware([DisallowHeaderAuthentication::class, 'auth:sanctum', 'signed', 'throttle:verification'])
    ->name('verification.verify');

Route::post('/email/verification-notification', EmailVerificationNotificationController::class)
    ->middleware([DisallowHeaderAuthentication::class, 'auth:sanctum', 'throttle:verification'])
    ->name('verification.send');

Route::middleware([DisallowHeaderAuthentication::class, 'auth:sanctum'])->group(static function (): void {
    Route::get('/auth/my-profile', MyProfileController::class)
        ->middleware(EnsureAccountNotDeactivated::class);

    Route::patch('/auth/my-profile', UpdateMyProfileController::class)
        ->middleware(EnsureAccountNotDeactivated::class);

    Route::put('/auth/password', UpdatePasswordController::class)
        ->middleware(EnsureAccountNotDeactivated::class);

    Route::post('/logout', LogoutController::class);
});
