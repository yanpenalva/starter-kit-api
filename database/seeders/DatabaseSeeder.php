<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * The only sanctioned seed entrypoint (`migrate:fresh --seed`).
 *
 * It exclusively refreshes the canonical RBAC catalogue. No user accounts,
 * role assignments, admin promotions or default credentials are ever created
 * here; RbacCatalogueSeeder enforces that invariant at runtime.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RbacCatalogueSeeder::class);
    }
}
