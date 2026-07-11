<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\TrainingProgram;
use App\Models\TrainingResourcePerson;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrainingResourcePersonController extends SahodayaAdminController
{
    public function index()
    {
        $resourcePersons = TrainingResourcePerson::forTenant($this->sahodaya->id)
            ->withCount(['sessions', 'programs'])
            ->orderBy('name')
            ->get();

        return $this->inertia('Sahodaya/Training/ResourcePersons', [
            'resourcePersons' => $resourcePersons,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        TrainingResourcePerson::create([
            ...$data,
            'tenant_id' => $this->sahodaya->id,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return back()->with('success', 'Resource person added.');
    }

    public function update(Request $request, string $tenantId, TrainingResourcePerson $resourcePerson)
    {
        abort_if($resourcePerson->tenant_id !== $this->sahodaya->id, 403);

        $data = $this->validated($request);
        $resourcePerson->update($data);

        return back()->with('success', 'Resource person updated.');
    }

    public function destroy(string $tenantId, TrainingResourcePerson $resourcePerson)
    {
        abort_if($resourcePerson->tenant_id !== $this->sahodaya->id, 403);

        if ($resourcePerson->sessions()->exists() || $resourcePerson->programs()->exists()) {
            $resourcePerson->update(['is_active' => false]);

            return back()->with('success', 'Resource person deactivated (still assigned to programs or sessions).');
        }

        $resourcePerson->delete();

        return back()->with('success', 'Resource person removed.');
    }

    public function assign(Request $request, string $tenantId, TrainingProgram $program)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'resource_person_id' => [
                'required',
                'integer',
                Rule::exists('training_resource_persons', 'id')
                    ->where('tenant_id', $this->sahodaya->id)
                    ->where('is_active', true),
            ],
            'honorarium' => 'nullable|numeric|min:0',
            'role' => 'nullable|string|max:100',
        ]);

        $program->resourcePersons()->syncWithoutDetaching([
            $data['resource_person_id'] => [
                'honorarium' => $data['honorarium'] ?? null,
                'role' => $data['role'] ?? null,
            ],
        ]);

        return back()->with('success', 'Resource person assigned to program.');
    }

    public function updateAssignment(
        Request $request,
        string $tenantId,
        TrainingProgram $program,
        TrainingResourcePerson $resourcePerson,
    ) {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($resourcePerson->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'honorarium' => 'nullable|numeric|min:0',
            'role' => 'nullable|string|max:100',
        ]);

        abort_unless(
            $program->resourcePersons()->where('training_resource_persons.id', $resourcePerson->id)->exists(),
            404,
            'Resource person is not assigned to this program.'
        );

        $program->resourcePersons()->updateExistingPivot($resourcePerson->id, [
            'honorarium' => $data['honorarium'] ?? null,
            'role' => $data['role'] ?? null,
        ]);

        return back()->with('success', 'Assignment updated.');
    }

    public function unassign(string $tenantId, TrainingProgram $program, TrainingResourcePerson $resourcePerson)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($resourcePerson->tenant_id !== $this->sahodaya->id, 403);

        $program->resourcePersons()->detach($resourcePerson->id);

        return back()->with('success', 'Resource person removed from program.');
    }

    /** @return array{name: string, email?: ?string, mobile?: ?string, designation?: ?string, bio?: ?string, is_active?: bool} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'mobile' => 'nullable|string|max:32',
            'designation' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:5000',
            'is_active' => 'nullable|boolean',
        ]);
    }
}
