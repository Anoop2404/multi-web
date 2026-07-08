<?php

namespace App\Models;

use App\Models\Concerns\UsesTenantConnectionWhenIsolated;
use App\Notifications\PortalVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, UsesTenantConnectionWhenIsolated;

    protected $guard_name = 'web';

    protected $fillable = [
        'tenant_id',
        'school_house_id',
        'name',
        'email',
        'username',
        'password',
        'must_change_password',
        'portal_welcome_seen',
        'last_login_at',
        'created_by_user_id',
        'group_classes',
    ];

    protected $hidden = [
        'password',
        'plain_password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'      => 'datetime',
            'password'               => 'hashed',
            'must_change_password'   => 'boolean',
            'portal_welcome_seen'    => 'boolean',
            'last_login_at'          => 'datetime',
            'group_classes'          => 'array',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->tenant_id === null && $this->hasRole('superadmin');
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new PortalVerifyEmail);
    }
}
