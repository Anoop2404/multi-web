<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExternalSahodaya;
use App\Models\FestStateProgram;
use App\Services\State\ExternalIntakeService;
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
        $sahodayas = ExternalSahodaya::where('state_program_id', $stateProgram->id)
            ->withCount('schools')
            ->orderBy('name')
            ->get();

        return inertia('StatePrograms/ExternalSahodayas', [
            'program'   => $stateProgram,
            'sahodayas' => $sahodayas,
            'portalUrl' => url('/state/external'),
        ]);
    }

    public function store(Request $request, FestStateProgram $stateProgram, ExternalIntakeService $service)
    {
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
        $externalSahodaya->update([
            'status' => $externalSahodaya->status === 'active' ? 'disabled' : 'active',
        ]);

        return back()->with('success', "{$externalSahodaya->name} is now {$externalSahodaya->status}.");
    }
}
