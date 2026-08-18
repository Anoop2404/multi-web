<?php

namespace App\Services\Auth;

use App\Models\ImpersonationSession;
use App\Models\PlatformUser;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantAuth;
use App\Support\TenantDomainSync;
use Illuminate\Support\Str;

/**
 * "Login as this tenant admin", audited (FRD-13 §12). Not a same-session dual-guard swap:
 * ResolveAuthenticationGuard forces the 'platform' guard unconditionally on the central
 * host, so a web-guard login started there would never actually take effect. Instead this
 * hands off via a short-lived single-use token to a bridge route on the TARGET TENANT's
 * own domain, where the web guard naturally applies — see routes/tenant.php's
 * 'impersonate/consume/{token}' route. The superadmin's own central-host session/cookie is
 * never touched, so it's still valid when they navigate back later.
 */
class ImpersonationService
{
    private const TOKEN_TTL_MINUTES = 2;

    public function start(PlatformUser $actor, Tenant $tenant, int $targetUserId, string $reason, ?string $ip): ImpersonationSession
    {
        $target = TenantAuth::withTenantUsers($tenant, fn () => User::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($targetUserId));

        $session = ImpersonationSession::create([
            'actor_platform_user_id' => $actor->id,
            'target_user_id' => $target->id,
            'target_tenant_id' => $tenant->id,
            'reason' => $reason,
            'consume_token' => Str::random(64),
            'token_expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES),
            'ip_address' => $ip,
        ]);

        return $session;
    }

    /** Where to send the browser to actually consume the token, on the tenant's own host. */
    public function consumeUrl(ImpersonationSession $session, Tenant $tenant): string
    {
        $base = TenantDomainSync::publicUrl($tenant)
            ?? ($tenant->subdomain ? 'https://'.TenantDomainSync::subdomainFqdn($tenant->subdomain) : null);

        abort_unless($base, 422, 'This tenant has no public domain or subdomain configured — impersonation needs one to hand off to.');

        return rtrim($base, '/')."/impersonate/consume/{$session->consume_token}";
    }

    /** @return array{session: ImpersonationSession, user: User}|null */
    public function consume(string $token, Tenant $currentTenant): ?array
    {
        $session = ImpersonationSession::where('consume_token', $token)->first();

        if (! $session
            || $session->target_tenant_id !== $currentTenant->id
            || $session->consumed_at !== null
            || $session->token_expires_at?->isPast()) {
            return null;
        }

        $target = User::query()->where('tenant_id', $currentTenant->id)->find($session->target_user_id);

        if (! $target) {
            return null;
        }

        $session->update([
            'consumed_at' => now(),
            'consume_token' => null,
        ]);

        return ['session' => $session, 'user' => $target];
    }

    public function end(ImpersonationSession $session): void
    {
        $session->update(['ended_at' => now()]);
    }
}
