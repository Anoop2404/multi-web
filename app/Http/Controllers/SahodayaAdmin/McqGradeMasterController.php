<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\McqGradeBand;
use App\Models\McqGradeMaster;
use Illuminate\Http\Request;

class McqGradeMasterController extends SahodayaAdminController
{
    public function index()
    {
        $masters = McqGradeMaster::where('tenant_id', $this->sahodaya->id)
            ->with('bands')
            ->orderByDesc('is_default')
            ->orderBy('title')
            ->get();

        return $this->inertia('Sahodaya/Mcq/GradeMasters/Index', [
            'masters' => $masters,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'      => 'required|string|max:120',
            'is_default' => 'nullable|boolean',
            'bands'      => 'required|array|min:1',
            'bands.*.label'           => 'required|string|max:20',
            'bands.*.min_percentage'  => 'required|numeric|min:0|max:100',
            'bands.*.max_percentage'  => 'required|numeric|min:0|max:100',
            'bands.*.is_pass'         => 'nullable|boolean',
            'bands.*.rank_eligible'   => 'nullable|boolean',
            'bands.*.display_order'   => 'nullable|integer|min:0',
        ]);

        if ($request->boolean('is_default')) {
            McqGradeMaster::where('tenant_id', $this->sahodaya->id)->update(['is_default' => false]);
        }

        $master = McqGradeMaster::create([
            'tenant_id'  => $this->sahodaya->id,
            'title'      => $data['title'],
            'is_default' => $request->boolean('is_default'),
            'is_active'  => true,
        ]);

        foreach ($data['bands'] as $i => $band) {
            McqGradeBand::create([
                'grade_master_id' => $master->id,
                'label'           => $band['label'],
                'min_percentage'  => $band['min_percentage'],
                'max_percentage'  => $band['max_percentage'],
                'is_pass'         => (bool) ($band['is_pass'] ?? true),
                'rank_eligible'   => (bool) ($band['rank_eligible'] ?? true),
                'display_order'   => $band['display_order'] ?? $i,
            ]);
        }

        return back()->with('success', 'Grade master created.');
    }

    public function update(Request $request, string $tenantId, McqGradeMaster $gradeMaster)
    {
        abort_if($gradeMaster->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'title'      => 'required|string|max:120',
            'is_default' => 'nullable|boolean',
            'is_active'  => 'nullable|boolean',
            'bands'      => 'required|array|min:1',
            'bands.*.label'           => 'required|string|max:20',
            'bands.*.min_percentage'  => 'required|numeric|min:0|max:100',
            'bands.*.max_percentage'  => 'required|numeric|min:0|max:100',
            'bands.*.is_pass'         => 'nullable|boolean',
            'bands.*.rank_eligible'   => 'nullable|boolean',
            'bands.*.display_order'   => 'nullable|integer|min:0',
        ]);

        if ($request->boolean('is_default')) {
            McqGradeMaster::where('tenant_id', $this->sahodaya->id)->where('id', '!=', $gradeMaster->id)->update(['is_default' => false]);
        }

        $gradeMaster->update([
            'title'      => $data['title'],
            'is_default' => $request->boolean('is_default'),
            'is_active'  => $request->boolean('is_active', true),
        ]);

        $gradeMaster->bands()->delete();
        foreach ($data['bands'] as $i => $band) {
            McqGradeBand::create([
                'grade_master_id' => $gradeMaster->id,
                'label'           => $band['label'],
                'min_percentage'  => $band['min_percentage'],
                'max_percentage'  => $band['max_percentage'],
                'is_pass'         => (bool) ($band['is_pass'] ?? true),
                'rank_eligible'   => (bool) ($band['rank_eligible'] ?? true),
                'display_order'   => $band['display_order'] ?? $i,
            ]);
        }

        return back()->with('success', 'Grade master updated.');
    }
}
