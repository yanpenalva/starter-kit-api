<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use Database\Seeders\RbacCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class RbacCatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $database = config('database.connections.'.config('database.default').'.database');
        $this->assertIsString($database);
        $this->assertSame($database, DB::connection()->getDatabaseName());
    }

    public function test_seeder_is_idempotent_and_matches_the_approved_catalogue(): void
    {
        $this->seed(RbacCatalogueSeeder::class);
        $this->seed(RbacCatalogueSeeder::class);

        $this->assertSame(count(Permission::cases()), DB::table('permissions')->where('guard_name', 'web')->count());
        $this->assertSame(2, DB::table('roles')->where('guard_name', 'web')->count());

        $permissionNames = DB::table('permissions')->orderBy('name')->pluck('name')->all();
        $expected = Permission::values();
        sort($expected);

        $this->assertSame($expected, array_values($permissionNames));

        $admin = DB::table('roles')->where('name', Role::Admin->value)->first();
        $this->assertNotNull($admin);

        $adminPermissions = DB::table('role_has_permissions')
            ->where('role_id', $admin->id)
            ->count();
        $this->assertSame(count(Permission::cases()), $adminPermissions);

        $plain = DB::table('roles')->where('name', Role::User->value)->first();
        $this->assertNotNull($plain);

        $plainPermissions = DB::table('role_has_permissions')
            ->where('role_id', $plain->id)
            ->count();
        $this->assertSame(0, $plainPermissions);
    }

    public function test_seeder_never_creates_accounts_or_role_assignments(): void
    {
        $this->seed(RbacCatalogueSeeder::class);

        $this->assertSame(0, DB::table('users')->count());
        $this->assertSame(0, DB::table('model_has_roles')->count());
    }
}
