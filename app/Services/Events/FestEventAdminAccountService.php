<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventAdminAccount;
use App\Models\FestEventStaff;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantDomainSync;
use App\Support\TenantUserCatalog;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class FestEventAdminAccountService
{
    public function ensure(FestEvent $event): ?FestEventAdminAccount
    {
        if (! Schema::hasTable('fest_event_admin_accounts') || $event->conducting_school_id
            || Tenant::find($event->tenant_id)?->type !== 'sahodaya') {
            return null;
        }

        return $event->getConnection()->transaction(function () use ($event) {
            FestEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($existing = FestEventAdminAccount::where('event_id', $event->id)->first()) {
                return $existing;
            }

            $username = 'event'.$event->id;
            for ($suffix = 2; User::where('username', $username)->exists(); $suffix++) {
                $username = 'event'.$event->id.'.'.$suffix;
            }
            $password = $this->password();
            $user = User::create([
                'tenant_id' => $event->tenant_id,
                'name' => $event->title.' — Event Admin',
                'username' => $username,
                'email' => 'event-'.$event->id.'-'.$event->tenant_id.'@portal.invalid',
                'password' => $password,
                'email_verified_at' => now(),
                'must_change_password' => true,
                'is_active' => true,
                'event_finance_restricted' => true,
            ]);
            $user->assignRole(Role::findOrCreate('event_admin', 'web'));
            $permissions = array_values(array_diff(
                TenantUserCatalog::defaultPermissionsForRole('event_admin'),
                ['fest.finance', 'finance.view'],
            ));
            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, 'web');
            }
            $user->syncPermissions($permissions);
            FestEventStaff::create(['event_id' => $event->id, 'user_id' => $user->id, 'duty' => 'event_admin']);

            return FestEventAdminAccount::create([
                'event_id' => $event->id, 'user_id' => $user->id, 'temporary_password' => $password,
                'password_fingerprint' => hash('sha256', $user->password),
            ]);
        });
    }

    public function reset(FestEventAdminAccount $account): void
    {
        $account->getConnection()->transaction(function () use ($account) {
            $password = $this->password();
            $account->user->forceFill(['password' => $password, 'must_change_password' => true,
                'plain_password' => null])->save();
            $account->update(['temporary_password' => $password,
                'password_fingerprint' => hash('sha256', $account->user->password)]);
        });
    }

    public function credentials(FestEventAdminAccount $account, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::findOrFail($account->event->tenant_id);
        $temporary = $account->temporary_password;
        $password = $account->user->must_change_password && $temporary
            && hash_equals($account->password_fingerprint, hash('sha256', $account->user->password)) ? $temporary : null;
        $loginUrl = rtrim(TenantDomainSync::publicUrl($tenant) ?: url('/'), '/').'/login';

        return [
            'event_id' => $account->event_id,
            'event_name' => $account->event->title,
            'username' => $account->user->username,
            'password' => $password,
            'login_url' => $loginUrl,
            'share_text' => $password === null ? null : "*{$account->event->title}*\nUsername: {$account->user->username}\nPassword: {$password}\nLogin: {$loginUrl}",
        ];
    }

    private function password(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $password = '';
        for ($i = 0; $i < 10; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }
}
