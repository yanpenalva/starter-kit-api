<?php

declare(strict_types=1);

namespace App\Enums;

enum Permission: string
{
    case UsersView = 'users.view';
    case UsersUpdate = 'users.update';
    case UsersSetStatus = 'users.set-status';
    case UsersAssignRoles = 'users.assign-roles';
    case UsersReadCpf = 'users.read-cpf';
    case UsersManage = 'users.manage';
    case RolesView = 'roles.view';
    case RolesUpdatePermissions = 'roles.update-permissions';
    case RolesManage = 'roles.manage';
    case LogsView = 'logs.view';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
