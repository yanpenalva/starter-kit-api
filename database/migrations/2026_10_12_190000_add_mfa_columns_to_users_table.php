<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->text('mfa_secret')->nullable();
            $table->text('mfa_recovery_codes')->nullable();
            $table->timestamp('mfa_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->dropColumn(['mfa_secret', 'mfa_recovery_codes', 'mfa_confirmed_at']);
        });
    }
};
