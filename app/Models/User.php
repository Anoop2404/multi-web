<?php

namespace App\Models;

use App\Models\Concerns\UsesTenantConnectionWhenIsolated;
use App\Notifications\PortalResetPassword;
use App\Notifications\PortalVerifyEmail;
use App\Services\Mail\SahodayaMailer;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, UsesTenantConnectionWhenIsolated;
    use HasRoles {
        hasPermissionTo as private unrestrictedHasPermissionTo;
        getAllPermissions as private unrestrictedGetAllPermissions;
    }

    public function automaticEventAdminAccount()
    {
        return $this->hasOne(FestEventAdminAccount::class);
    }

    public function hasRestrictedEventFinance(): bool
    {
        return (bool) $this->event_finance_restricted;
    }

    public function hasPermissionTo($permission, ?string $guardName = null): bool
    {
        $name = is_string($permission) ? $permission : ($permission->name ?? null);
        if (in_array($name, ['fest.finance', 'finance.view'], true) && $this->hasRestrictedEventFinance()) {
            return false;
        }

        return $this->unrestrictedHasPermissionTo($permission, $guardName);
    }

    public function getAllPermissions(): Collection
    {
        $permissions = $this->unrestrictedGetAllPermissions();

        return $this->hasRestrictedEventFinance()
            ? $permissions->reject(fn ($permission) => in_array($permission->name, ['fest.finance', 'finance.view'], true))->values()
            : $permissions;
    }

    protected $guard_name = 'web';

    protected $fillable = [
        'tenant_id',
        'school_house_id',
        'name',
        'email',
        'username',
        'password',
        'email_verified_at',
        'must_change_password',
        'is_active',
        'portal_welcome_seen',
        'last_login_at',
        'created_by_user_id',
        'group_classes',
        'event_finance_restricted',
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
            'is_active'               => 'boolean',
            'portal_welcome_seen'    => 'boolean',
            'last_login_at'          => 'datetime',
            'group_classes'          => 'array',
            'event_finance_restricted' => 'boolean',
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

    public function isStateUser(): bool
    {
        if ($this->tenant_id !== null) {
            return false;
        }

        try {
            if ($this->hasAnyRole(['state_admin', 'state_staff'])) {
                return true;
            }
        } catch (\Throwable) {
        }

        return DB::connection(config('tenancy.database.central_connection', 'central'))
            ->table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereIn('roles.name', ['state_admin', 'state_staff'])
            ->where('roles.guard_name', $this->guard_name)
            ->where('model_has_roles.model_id', $this->id)
            ->whereIn('model_has_roles.model_type', [self::class, PlatformUser::class])
            ->exists();
    }

    public function hasStateStaffRole(): bool
    {
        if ($this->tenant_id !== null) {
            return false;
        }

        try {
            if ($this->hasRole('state_staff')) {
                return true;
            }
        } catch (\Throwable) {
        }

        return DB::connection(config('tenancy.database.central_connection', 'central'))
            ->table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'state_staff')
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
