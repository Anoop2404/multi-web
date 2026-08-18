<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ImpersonationSession;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Auth\ImpersonationService;
use App\Support\TenantDomainSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Tenant-hosted half of impersonation — the central-host ImpersonationController only
 * creates the audit record + hands off a one-time token here, since the 'web' guard
 * only actually takes effect on the tenant's own host (see ImpersonationService's
 * class docblock for why a same-session central-host login attempt would silently no-op).
 */
class ImpersonationBridgeController extends Controller
{
    public function consume(Request $request, string $token, ImpersonationService $impersonation, PlatformAuditLogger $audit)
    {
        $tenant = tenant();
        abort_unless($tenant, 404);

        $result = $impersonation->consume($token, $tenant);

        if (! $result) {
            abort(403, 'This impersonation link is invalid, expired, or already used.');
        }

        Auth::guard('web')->login($result['user']);
        $request->session()->put('impersonation_session_id', $result['session']->id);
        $request->session()->regenerate();

        $audit->impersonationConsumed($result['session']);

        $landing = $tenant->type === 'school'
            ? "/school-admin/{$tenant->id}"
            : "/sahodaya-admin/{$tenant->id}";

        return redirect($landing)->with('success', 'You are now viewing this account as '.$result['user']->name.'.');
    }

    public function end(Request $request, ImpersonationService $impersonation, PlatformAuditLogger $audit)
    {
        $sessionId = $request->session()->get('impersonation_session_id');
        $session = $sessionId ? ImpersonationSession::find($sessionId) : null;

        if ($session && $session->isActive()) {
            $impersonation->end($session);
            $audit->impersonationEnded($session);
        }

        $tenant = tenant();
        $request->session()->forget('impersonation_session_id');
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $loginUrl = $tenant ? (TenantDomainSync::publicUrl($tenant) ?? '/') : '/';

        return redirect(rtrim((string) $loginUrl, '/').'/login')->with('info', 'Impersonation ended.');
    }
}
