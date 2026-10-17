<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\FestEvent;
use App\Models\Tenant;
use App\Support\ProgramRouteMap;
use App\Support\TenantBranding;
use App\Support\TenantUserCatalog;
use Illuminate\Http\Request;

/** One shareable URL; after login each school is routed to its own protected choices. */
class StateSlotEntryController extends Controller
{
    public function __invoke(Request $request, FestEvent $event)
    {
        $tenant = TenantBranding::resolveTenant($request);
        abort_unless($tenant?->type === 'sahodaya' && $event->tenant_id === $tenant->id, 404);
        abort_unless($event->state_program_id && ! $event->parent_event_id, 404);
        if (! $request->user()) {
            return redirect()->guest('/school-login');
        }
        $school = Tenant::whereKey($request->user()->tenant_id)->where('type', 'school')->where('parent_id', $tenant->id)->first();
        abort_unless($school && $request->user()->hasAnyRole(TenantUserCatalog::schoolPanelRoles()), 403, 'Sign in with a school account belonging to this Sahodaya.');
        $slug = ProgramRouteMap::slugFromEventType($event->event_type);
        abort_unless($slug, 404);
        $base = ProgramRouteMap::schoolBase($school->id, ProgramRouteMap::prefixFromSlug($slug));
        return redirect($base.'/events/'.$event->id.'/state-slots');
    }
}
