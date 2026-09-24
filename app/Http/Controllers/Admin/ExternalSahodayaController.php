<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExternalSahodaya;
use App\Models\FestStateProgram;
use App\Services\State\ExternalIntakeService;
use App\Support\StateScope;
use App\Support\TenantDomainSync;
use Illuminate\Http\Request;

/**
 * State-admin side of the outside-Sahodaya intake (docs/STATE_LEVEL_KALOTSAV_ROLLOUT_PLAN.md §2.1).
 * Creating a row here and handing its access code to a Sahodaya coordinator is the entire
 * "onboarding" step for a Sahodaya that isn't a platform tenant — no subdomain, no database.
 */
class ExternalSahodayaController extends Controller
{
    public function index(FestStateProgram $stateProgram)
    {
        StateScope::assertOwns($stateProgram->state_id);
        // The tenant relation is what tells this page which rows have been promoted and are no
        // longer reachable by access code — without it the page hands out codes for Sahodayas that
        // have already moved onto the platform (see the promotion pipeline in
        // docs/STATE_ADMIN_TENANT_PROMOTION_AND_UAT_PLAN_2026_09_23.md §4).
        $sahodayas = ExternalSahodaya::where('state_program_id', $stateProgram->id)
            ->withCount('schools')
            ->with('tenant:id,name,subdomain,domain,is_active')
            ->orderBy('district')
            ->orderBy('name')
            ->get()
            ->map(function (ExternalSahodaya $sahodaya) {
                $row = $sahodaya->toArray();
                $row['tenant_url'] = $sahodaya->tenant
                    ? TenantDomainSync::publicUrl($sahodaya->tenant)
                    : null;

                return $row;
            });

        return inertia('StatePrograms/ExternalSahodayas', [
            'program'   => $stateProgram,
            'sahodayas' => $sahodayas,
            'portalUrl' => url('/state/external'),
        ]);
    }

    public function store(Request $request, FestStateProgram $stateProgram, ExternalIntakeService $service)
    {
        StateScope::assertOwns($stateProgram->state_id);
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'contact_name'  => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:40',
            'contact_email' => 'nullable|email|max:255',
        ]);

        $sahodaya = $service->createSahodaya($stateProgram, $data);

        return back()->with(
            'success',
            "Added \"{$sahodaya->name}\". Share access code {$sahodaya->access_code} with their coordinator (portal: /state/external)."
        );
    }

    public function toggleStatus(ExternalSahodaya $externalSahodaya)
    {
        StateScope::assertOwns(FestStateProgram::find($externalSahodaya->state_program_id)?->state_id);
        $externalSahodaya->update([
            'status' => $externalSahodaya->status === 'active' ? 'disabled' : 'active',
        ]);

        return back()->with('success', "{$externalSahodaya->name} is now {$externalSahodaya->status}.");
    }
}
