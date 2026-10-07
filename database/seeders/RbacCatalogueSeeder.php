<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

final class RbacCatalogueSeeder extends Seeder
{
    public const GUARD = 'web';

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::cases() as $permission) {
            PermissionModel::firstOrCreate(
                ['name' => $permission->value, 'guard_name' => self::GUARD],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }

        $admin = RoleModel::firstOrCreate(
            ['name' => Role::Admin->value, 'guard_name' => self::GUARD],
            ['created_at' => now(), 'updated_at' => now()],
        );
        $user = RoleModel::firstOrCreate(
            ['name' => Role::User->value, 'guard_name' => self::GUARD],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $admin->syncPermissions(array_map(
            static fn (Permission $permission): string => $permission->value,
            Permission::cases(),
        ));

        $user->syncPermissions([]);

        $this->assertCatalogueIntegrity();

        $this->assertNoUserAssignments();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function assertCatalogueIntegrity(): void
    {
        $permissionNames = DB::table('permissions')
            ->where('guard_name', self::GUARD)
            ->orderBy('name')
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        $expectedPermissions = Permission::values();
        sort($expectedPermissions);

        if (array_values($permissionNames) !== $expectedPermissions) {
            throw new RuntimeException('Permission catalogue diverged from the approved set.');
        }

        $roleNames = DB::table('roles')
            ->where('guard_name', self::GUARD)
            ->orderBy('name')
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        $expectedRoles = Role::values();
        sort($expectedRoles);

        if (array_values($roleNames) !== $expectedRoles) {
            throw new RuntimeException('Role catalogue diverged from the approved set.');
        }
    }

    private function assertNoUserAssignments(): void
    {
        $assignedRoles = (int) DB::table('model_has_roles')->count();

        if ($assignedRoles !== 0) {
            throw new RuntimeException('Seeders must never assign roles to accounts.');
        }
    }
}
