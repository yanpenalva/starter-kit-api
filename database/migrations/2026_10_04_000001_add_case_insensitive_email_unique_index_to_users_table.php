<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {

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

        DB::statement('CREATE UNIQUE INDEX users_email_lower_unique ON users (lower(btrim(email, E\' \\t\\n\\r\\x0B\')))');
    }

    public function down(): void
    {

        DB::statement('DROP INDEX IF EXISTS users_email_lower_unique');
    }
};
