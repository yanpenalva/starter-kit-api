<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;

    /**
     * The single guard used by the catalogue and every permission check.
     */
    protected string $guard_name = 'web';

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'deactivated_at',
        'cpf',
        'password',
        'remember_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'cpf',
    ];

    public function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Excluding a target keeps at least one admin standing.
     */
    public static function otherAdminsRemain(int $targetId): bool
    {
        return self::query()
            ->whereKeyNot($targetId)
            ->role(Role::Admin->value)
            ->exists();
    }
}
