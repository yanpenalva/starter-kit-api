<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use Illuminate\Validation\Rule;

final class RoleNameRules
{
    /** @return array<string, array<int, mixed>> */
    public static function validationRules(): array
    {
        return [
            'roles.*' => ['string', 'distinct', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }
}
