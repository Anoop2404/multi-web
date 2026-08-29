<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformState;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * FRD-13 §2 State Management. Deliberately named distinctly from StateUserController
 * (manages platform_user accounts within a state) and StateAdminDashboardController
 * (a state_admin's own dashboard) — this one manages the State entity itself.
 */
class StateController extends Controller
{
    public function index()
    {
        $states = PlatformState::withCount('platformUsers')
            ->orderBy('name')
            ->get();

        return inertia('States/Index', [
            'states' => $states,
        ]);
    }

    public function store(Request $request, PlatformAuditLogger $audit)
    {
        $data = $this->validated($request);

        $state = PlatformState::create($data);

        $audit->log('state.created', "State created: {$state->name}", $state, ['state_id' => $state->id], category: 'platform');

        return back()->with('success', 'State created.');
    }

    public function update(Request $request, PlatformState $state, PlatformAuditLogger $audit)
    {
        $data = $this->validated($request, $state);

        $state->update($data);

        $audit->log('state.updated', "State updated: {$state->name}", $state, ['state_id' => $state->id, 'changes' => $state->getChanges()], category: 'platform');

        return back()->with('success', 'State updated.');
    }

    public function destroy(PlatformState $state, PlatformAuditLogger $audit)
    {
        abort_if($state->platformUsers()->exists(), 422, 'Reassign or remove this state\'s users before deleting it.');
        abort_if($state->festStatePrograms()->exists(), 422, 'This state has fest programs on record — archive it instead of deleting.');

        $name = $state->name;
        $state->delete();

        $audit->log('state.deleted', "State deleted: {$name}", null, ['state_id' => $state->id], category: 'platform');

        return back()->with('success', 'State removed.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?PlatformState $state = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('states', 'code')->ignore($state?->id)],
            'name' => 'required|string|max:255',
            'is_active' => 'sometimes|boolean',
            'contact_name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:30',
            'default_academic_year' => 'nullable|string|max:20',
            'financial_year_start_month' => 'nullable|string|max:20',
        ]);
    }
}
