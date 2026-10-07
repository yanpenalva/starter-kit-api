<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string INDEX_NAME = 'activity_log_event_id_index';

    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->index(['event', 'id'], self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropIndex(self::INDEX_NAME);
        });
    }
};
