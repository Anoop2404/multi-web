<?php

namespace App\Models;

use App\Models\Concerns\UsesTenantConnectionWhenIsolated;
use App\Notifications\PortalResetPassword;
use App\Notifications\PortalVerifyEmail;
use App\Services\Mail\SahodayaMailer;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
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
        if ($this->tenant_id !== null) {
            return false;
        }

        if ($this->hasRole('superadmin')) {
            return true;
        }

        return DB::connection(config('tenancy.database.central_connection', 'central'))
            ->table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'superadmin')
            ->where('roles.guard_name', $this->guard_name)
            ->where('model_has_roles.model_id', $this->id)
            ->whereIn('model_has_roles.model_type', [self::class, PlatformUser::class])
            ->exists();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new PortalVerifyEmail);
    }

    public function sendPasswordResetNotification($token): void
    {
        $school = $this->tenant_id ? Tenant::query()->find($this->tenant_id) : null;
        $sahodayaId = $school?->parent_id ?: $school?->id;

        if ($sahodayaId) {
            SahodayaMailer::for($sahodayaId)->sendPasswordReset($this, $token);

            return;
        }

        $this->notify(new PortalResetPassword($token));
    }
}
