<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEventItem;
use App\Models\FestTaxonomyMaster;
use App\Services\Events\FestTaxonomyRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

use App\Support\ProgramRouteMap;

class FestTaxonomyMasterController extends SahodayaAdminController
{
    public function index(Request $request, FestTaxonomyRegistry $registry)
    {
        $registry->forTenant($this->sahodaya->id)->ensureDefaults();

        $dimension = $request->input('dimension', 'sport_discipline');
        abort_unless(array_key_exists($dimension, FestTaxonomyMaster::DIMENSIONS), 404);

        $programSlug = $request->input('program');
        $navProps = $programSlug && ProgramRouteMap::eventTypeFromSlug($programSlug)
            ? $this->programNavProps($programSlug)
            : [];

        return $this->inertia('Sahodaya/TaxonomyMasters/Index', $navProps + [
            'dimension'   => $dimension,
            'dimensions'  => FestTaxonomyMaster::DIMENSIONS,
            'entries'     => $registry->forTenant($this->sahodaya->id)->rowsForDimension($dimension),
            'sportsAgeGroupsUrl' => "/sahodaya-admin/{$this->sahodaya->id}/sports/age-groups",
        ]);
    }

    public function store(Request $request)
    {
        $dimension = $request->input('dimension', 'sport_discipline');
        abort_unless(array_key_exists($dimension, FestTaxonomyMaster::DIMENSIONS), 422);

        $data = $request->validate([
            'dimension'  => ['required', Rule::in(array_keys(FestTaxonomyMaster::DIMENSIONS))],
            'entry_key'  => [
                'required', 'string', 'max:60', 'regex:/^[a-z0-9_.]+$/',
                Rule::unique('fest_taxonomy_masters', 'entry_key')
                    ->where('tenant_id', $this->sahodaya->id)
                    ->where('dimension', $dimension),
            ],
            'label'      => 'required|string|max:120',
            'sort_order' => 'nullable|integer|min:0|max:999',
        ]);

        FestTaxonomyMaster::create([
            'tenant_id'  => $this->sahodaya->id,
            'dimension'  => $data['dimension'],
            'entry_key'  => strtolower($data['entry_key']),
            'label'      => $data['label'],
            'sort_order' => $data['sort_order'] ?? 100,
            'is_active'  => true,
        ]);

        return back()->with('success', 'Master entry added.');
    }

    public function update(Request $request, string $tenantId, FestTaxonomyMaster $taxonomyMaster)
    {
        abort_if($taxonomyMaster->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'label'      => 'required|string|max:120',
            'sort_order' => 'nullable|integer|min:0|max:999',
            'is_active'  => 'nullable|boolean',
        ]);

        $taxonomyMaster->update($data);

        return back()->with('success', 'Master entry updated.');
    }

    public function destroy(string $tenantId, FestTaxonomyMaster $taxonomyMaster)
    {
        abort_if($taxonomyMaster->tenant_id !== $this->sahodaya->id, 403);

        $inUse = $this->entryInUse($taxonomyMaster);
        if ($inUse) {
            $taxonomyMaster->update(['is_active' => false]);

            return back()->with('success', 'Entry deactivated (in use by event items).');
        }

        $taxonomyMaster->delete();

        return back()->with('success', 'Master entry removed.');
    }

    public function resetDefaults(Request $request, FestTaxonomyRegistry $registry)
    {
        $dimension = $request->input('dimension');
        abort_unless($dimension && array_key_exists($dimension, FestTaxonomyMaster::DIMENSIONS), 422);

        FestTaxonomyMaster::where('tenant_id', $this->sahodaya->id)
            ->where('dimension', $dimension)
            ->delete();

        $registry->forTenant($this->sahodaya->id)->ensureDefaults($dimension);

        return back()->with('success', 'Reset to system defaults.');
    }

    private function entryInUse(FestTaxonomyMaster $entry): bool
    {
        $column = match ($entry->dimension) {
            'sport_discipline'   => 'sport_discipline',
            'venue_type'         => 'venue_type',
            'competition_format' => 'competition_format',
            'participant_type'   => 'participant_type',
            'stage_type'         => 'stage_type',
            'arts_category'      => 'category',
            'class_group'        => 'class_group',
            'gender'             => 'gender',
            default              => null,
        };

        if (! $column) {
            return false;
        }

        return FestEventItem::whereHas('event', fn ($q) => $q->where('tenant_id', $this->sahodaya->id))
            ->where($column, $entry->entry_key)
            ->exists();
    }
}
