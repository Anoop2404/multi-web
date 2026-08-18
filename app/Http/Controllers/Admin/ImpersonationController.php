<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Auth\ImpersonationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ImpersonationController extends Controller
{
    public function start(Request $request, Tenant $tenant, int $user, ImpersonationService $impersonation, PlatformAuditLogger $audit)
    {
        abort_unless(in_array($tenant->type, ['sahodaya', 'school'], true), 404);

        $data = $request->validate([
            'reason' => 'required|string|min:5|max:1000',
        ]);

        $session = $impersonation->start($request->user(), $tenant, $user, $data['reason'], $request->ip());

        $audit->impersonationStarted($session);

        return Inertia::location($impersonation->consumeUrl($session, $tenant));
    }
}
