<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\ActiveSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MfaChallengeController;
use App\Http\Controllers\Auth\MfaEnrollmentController;
use App\Http\Controllers\Auth\MyProfileController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Middleware\DisallowHeaderAuthentication;
use App\Http\Middleware\EnsureAccountNotDeactivated;
use App\Http\Middleware\EnsureMfaChallenge;
use App\Http\Middleware\EnsureStatefulSession;
use Illuminate\Support\Facades\Route;

Route::post('/login', LoginController::class)
    ->middleware([DisallowHeaderAuthentication::class, 'throttle:login', EnsureStatefulSession::class]);

Route::post('/auth/mfa/challenge', MfaChallengeController::class)
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
    ->middleware([DisallowHeaderAuthentication::class, 'auth:sanctum', EnsureMfaChallenge::class, 'signed', 'throttle:verification'])
    ->name('verification.verify');

Route::post('/email/verification-notification', EmailVerificationNotificationController::class)
    ->middleware([DisallowHeaderAuthentication::class, 'auth:sanctum', EnsureMfaChallenge::class, 'throttle:verification'])
    ->name('verification.send');

Route::middleware([DisallowHeaderAuthentication::class, 'auth:sanctum', EnsureMfaChallenge::class])->group(static function (): void {
    Route::get('/auth/my-profile', MyProfileController::class)
        ->middleware(EnsureAccountNotDeactivated::class);

    Route::middleware(EnsureAccountNotDeactivated::class)->group(static function (): void {
        Route::get('/auth/sessions', [ActiveSessionController::class, 'index']);
        Route::delete('/auth/sessions', [ActiveSessionController::class, 'destroyOthers'])
            ->middleware('throttle:sessions');
        Route::delete('/auth/sessions/{id}', [ActiveSessionController::class, 'destroy'])
            ->middleware('throttle:sessions');
    });

    Route::post('/logout', LogoutController::class);

    Route::get('/auth/mfa/status', [MfaEnrollmentController::class, 'status']);
    Route::post('/auth/mfa/enroll', [MfaEnrollmentController::class, 'enroll']);
    Route::post('/auth/mfa/confirm', [MfaEnrollmentController::class, 'confirm']);
    Route::post('/auth/mfa/disable', [MfaEnrollmentController::class, 'disable']);
    Route::post('/auth/mfa/recovery-codes', [MfaEnrollmentController::class, 'regenerateRecoveryCodes']);
});
