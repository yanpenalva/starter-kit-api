<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Role;
use App\Notifications\ResetPassword as ResetPasswordNotification;
use App\Notifications\VerifyEmail;
use App\Support\BlockedAccount;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $deactivated_at
 * @property string|null $cpf
 * @property string $password
 * @property string|null $remember_token
 * @property string|null $mfa_secret
 * @property array<int, string>|null $mfa_recovery_codes
 * @property Carbon|null $mfa_confirmed_at
 */
final class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;

    protected string $guard_name = 'web';

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'deactivated_at',
        'cpf',
        'password',
        'remember_token',
        'mfa_secret',
        'mfa_recovery_codes',
        'mfa_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'cpf',
        'mfa_secret',
        'mfa_recovery_codes',
    ];

    public function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'password' => 'hashed',
            'mfa_secret' => 'encrypted',
            'mfa_recovery_codes' => 'array',
            'mfa_confirmed_at' => 'datetime',
        ];
    }

    public function hasMfaEnabled(): bool
    {
        return $this->mfa_confirmed_at !== null && ! empty($this->mfa_secret);
    }

    public function markEmailAsVerified(): bool
    {
        if (BlockedAccount::reason($this) !== BlockedAccount::REASON_PENDING_APPROVAL) {
            return false;
        }

        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
            'deactivated_at' => null,
        ])->save();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify((new VerifyEmail)->afterCommit());
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify((new ResetPasswordNotification($token))->afterCommit());
    }

    public static function otherAdminsRemain(int $targetId): bool
    {
        return self::query()
            ->whereKeyNot($targetId)
            ->whereNull('deactivated_at')
            ->role(Role::Admin->value)
            ->exists();
    }

    public function isPrivilegedAccount(): bool
    {
        return $this->hasRole(Role::Admin->value)
            || $this->hasAnyPermission([
                Permission::UsersManage->value,
                Permission::UsersUpdate->value,
                Permission::UsersSetStatus->value,
                Permission::UsersAssignRoles->value,
                Permission::UsersView->value,
                Permission::UsersReadCpf->value,
                Permission::RolesView->value,
                Permission::RolesUpdatePermissions->value,
                Permission::RolesManage->value,
                Permission::LogsView->value,
            ]);
    }

    public function isPrivilegedOperator(): bool
    {
        return $this->hasRole(Role::Admin->value)
            || $this->hasPermissionTo(Permission::UsersManage->value);
    }
}
