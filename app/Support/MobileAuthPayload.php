<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\User;

class MobileAuthPayload
{
    public static function for(User $user): array
    {
        $user->loadMissing('tenant');
        $tenant = $user->tenant;

        $role = $user->hasRole('sahodaya_admin')
            ? 'sahodaya_admin'
            : ($user->hasRole('school_admin') ? 'school_admin' : null);

        $logoUrl = self::logoUrlFor($tenant);

        return [
            'user' => [
                'id'                => $user->id,
                'name'              => $user->name,
                'email'             => $user->email,
                'email_verified'    => $user->hasVerifiedEmail(),
                'role'              => $role,
                'tenant_id'         => $user->tenant_id,
                'tenant_name'       => $tenant?->name,
                'tenant_type'       => $tenant?->type,
                'logo_url'          => $logoUrl,
                'membership_status' => $tenant?->type === 'school' ? $tenant->membership_status : null,
            ],
            'role'        => $role,
            'tenant_id'   => $user->tenant_id,
            'tenant_name' => $tenant?->name,
            'tenant_type' => $tenant?->type,
            'logo_url'    => $logoUrl,
        ];
    }

    private static function logoUrlFor(?Tenant $tenant): ?string
    {
        $logoPath = TenantBranding::logoUrl($tenant);
        if (! $logoPath) {
            return null;
        }

        return str_starts_with($logoPath, 'http') ? $logoPath : url($logoPath);
    }

    public static function assertCanLogin(User $user): ?string
    {
        if ($user->isSuperAdmin()) {
            return 'Superadmin accounts cannot use the mobile app.';
        }

        if (! $user->hasAnyRole(['school_admin', 'sahodaya_admin'])) {
            return 'This account is not authorized for the mobile app.';
        }

        if ($user->hasRole('school_admin') && ! $user->hasVerifiedEmail()) {
            return 'Please verify your email before signing in.';
        }

        if ($user->hasRole('school_admin') && $user->tenant_id) {
            $school = Tenant::find($user->tenant_id);
            if ($school?->membership_status === 'rejected') {
                return 'Your school application was rejected.';
            }
        }

        return null;
    }
}
