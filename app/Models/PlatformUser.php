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
        try {
            if ($this->hasRole('superadmin')) {
                return true;
            }
        } catch (\Throwable) {
            // Spatie may hit a non-taggable tenant cache store; fall through to central DB.
        }

        // Fallback when Spatie cache is unavailable under a non-taggable tenant cache store.
        return \Illuminate\Support\Facades\DB::connection(config('tenancy.database.central_connection', 'central'))
            ->table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'superadmin')
            ->where('roles.guard_name', $this->guard_name)
            ->where('model_has_roles.model_id', $this->id)
            ->whereIn('model_has_roles.model_type', [self::class, User::class])
            ->exists();
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
