<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (app()->environment(['local', 'testing'])) {
    Schedule::command('infra:probe')->everyMinute();
}

Artisan::command('auth:prune-expired-tokens', function (): void {
    $expire = config('auth.passwords.users.expire', 60);
    $resetTokenExpiry = now()->subMinutes(is_numeric($expire) ? (int) $expire : 60);
    $deletedResetTokens = DB::table('password_reset_tokens')
        ->where('created_at', '<', $resetTokenExpiry)
        ->delete();

    $verificationExpiry = now()->subHours(24);
    $deletedUnverifiedUsers = DB::table('users')
        ->whereNull('email_verified_at')
        ->where('created_at', '<', $verificationExpiry)
        ->delete();

    $this->info(sprintf(
        'Expired reset tokens removed: %d; expired unverified accounts removed: %d.',
        $deletedResetTokens,
        $deletedUnverifiedUsers,
    ));
})->purpose('Remove expired password reset tokens and email verification accounts');

Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('auth:prune-expired-tokens')->daily();
Schedule::command('activitylog:clean')->daily();
