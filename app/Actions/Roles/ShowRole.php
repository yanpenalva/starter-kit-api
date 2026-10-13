<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use Spatie\Permission\Models\Role;

final class ShowRole
{
    public function run(Role $role): Role
    {
        return $role->load('permissions');
    }
}
