<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    /**
     * Identity fields only. Privileged authorization fields (roles,
     * permissions) are deliberately absent until their owning wave.
     */
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
}
