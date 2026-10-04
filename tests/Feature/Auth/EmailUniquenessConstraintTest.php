<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

interface TestableMigration
{
    public function up(): void;

    public function down(): void;
}

final class EmailUniquenessConstraintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Guarded destructive operations target the isolated test database only.
        $database = config('database.connections.pgsql.database');
        $this->assertIsString($database);
        $this->assertSame('starter_api_test', $database);
    }

    public function test_duplicate_case_variant_email_is_rejected_by_the_database(): void
    {
        User::factory()->create([
            'email' => 'jane.doe@example.test',
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('users_email_lower_unique');

        DB::table('users')->insert([
            'name' => 'Jane Variant',
            'email' => 'JANE.DOE@EXAMPLE.TEST',
            'password' => 'password',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_concurrent_duplicate_case_variant_email_insert_waits_on_the_unique_index(): void
    {
        User::factory()->create([
            'email' => 'concurrency.test@example.test',
        ]);

        config(['database.connections.conc' => config('database.connections.pgsql')]);
        $concurrent = DB::connection('conc');
        $concurrent->statement("SET lock_timeout TO '500ms'");

        try {
            $concurrent->table('users')->insert([
                'name' => 'Concurrent Variant',
                'email' => 'CONCURRENCY.TEST@EXAMPLE.TEST',
                'password' => 'password',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('The concurrent duplicate case-variant insert must not succeed.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString(
                'users_email_lower_unique',
                (string) $exception->getMessage(),
            );
        }
    }

    public function test_migration_fails_closed_without_modifying_preexisting_case_colliding_data(): void
    {
        /** @var TestableMigration $migration */
        $migration = require database_path(
            'migrations/2026_10_04_000001_add_case_insensitive_email_unique_index_to_users_table.php',
        );

        $migration->down();

        DB::table('users')->insert([
            [
                'name' => 'Collision User 1',
                'email' => 'collision@example.test',
                'password' => 'secret',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Collision User 2',
                'email' => 'COLLISION@EXAMPLE.TEST',
                'password' => 'secret',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->assertSame(2, DB::table('users')->whereRaw("lower(email) = 'collision@example.test'")->count());

        try {
            $migration->up();
            $this->fail('Migration must throw RuntimeException when colliding emails exist.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'conflicting case-variant email addresses exist',
                $exception->getMessage(),
            );
        }

        // Verify data was NOT deleted or rewritten
        $this->assertSame(2, DB::table('users')->whereRaw("lower(email) = 'collision@example.test'")->count());
        $this->assertEqualsCanonicalizing(
            ['collision@example.test', 'COLLISION@EXAMPLE.TEST'],
            DB::table('users')
                ->whereRaw("lower(email) = 'collision@example.test'")
                ->pluck('email')
                ->all(),
        );

        // Clean up colliding data so subsequent tests or teardown are clean
        DB::table('users')->where('email', 'COLLISION@EXAMPLE.TEST')->delete();
        $migration->up();
    }

    public function test_migration_rollback_removes_only_the_lower_unique_index_and_preserves_data(): void
    {
        $user = User::factory()->create([
            'email' => 'preserve@example.test',
        ]);

        /** @var TestableMigration $migration */
        $migration = require database_path(
            'migrations/2026_10_04_000001_add_case_insensitive_email_unique_index_to_users_table.php',
        );

        $migration->down();

        // Index should no longer exist in pg_indexes
        $indexExists = DB::selectOne(
            "SELECT 1 FROM pg_indexes WHERE tablename = 'users' AND indexname = 'users_email_lower_unique'",
        );
        $this->assertNull($indexExists);

        // Original unique index still exists
        $originalIndex = DB::selectOne(
            "SELECT 1 FROM pg_indexes WHERE tablename = 'users' AND indexname = 'users_email_unique'",
        );
        $this->assertNotNull($originalIndex);

        // Data is preserved intact
        $this->assertTrue(DB::table('users')->where('id', $user->id)->exists());

        // Restore index
        $migration->up();
    }
}
