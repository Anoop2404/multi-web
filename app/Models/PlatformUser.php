<?php

namespace App\Models;

use App\Notifications\PortalVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Platform operators that live only in the central database (superadmin, state admin).
 */
class PlatformUser extends Authenticatable implements MustVerifyEmail
{
    use CentralConnection, HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $table = 'users';

    protected $guard_name = 'web';

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'username',
        'password',
        'must_change_password',
        'portal_welcome_seen',
        'last_login_at',
        'created_by_user_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'password'             => 'hashed',
            'must_change_password' => 'boolean',
            'portal_welcome_seen'  => 'boolean',
            'last_login_at'        => 'datetime',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('superadmin');
    }

    public function getRoleClass(): string
    {
        return PlatformRole::class;
    }

    public function getPermissionClass(): string
    {
        return PlatformPermission::class;
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new PortalVerifyEmail);
    }
}
