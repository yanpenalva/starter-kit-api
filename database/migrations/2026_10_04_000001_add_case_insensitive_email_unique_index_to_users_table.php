<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Portable collision check first: lower(trim(email)) behaves the
        // same on PostgreSQL and SQLite; the engine-level expression index
        // below is PostgreSQL-only and skipped elsewhere (local sqlite runs).
        $hasCollisions = DB::table('users')
            ->selectRaw('1')
            ->groupByRaw('lower(trim(email))')
            ->havingRaw('count(*) > 1')
            ->exists();

        if ($hasCollisions) {
            throw new RuntimeException(
                'Cannot add case-insensitive email unique index: conflicting case-variant email addresses exist in users table.',
            );
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Minimal PostgreSQL expression index on lower(btrim(email, E' \t\n\r\x0B')).
        // Enforces case-insensitive and whitespace-trimmed email uniqueness at the storage engine level.
        DB::statement('CREATE UNIQUE INDEX users_email_lower_unique ON users (lower(btrim(email, E\' \\t\\n\\r\\x0B\')))');
    }

    public function down(): void
    {
        // Rollback removes ONLY the new index, preserving the prior unique constraint and all table data.
        DB::statement('DROP INDEX IF EXISTS users_email_lower_unique');
    }
};
