<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\PersistDefaults;
use App\Models\FestEventItem;
use App\Models\FestSportsAgeGroupConfig;
use App\Models\SahodayaProfile;
use App\Services\Events\FestSportsAgeGroupRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SportsAgeGroupController extends SahodayaAdminController
{
    public function index(FestSportsAgeGroupRegistry $registry)
    {
        $registry->forTenant($this->sahodaya->id)->ensureDefaults();

        $groups = FestSportsAgeGroupConfig::where('tenant_id', $this->sahodaya->id)
            ->orderBy('sort_order')
            ->orderBy('group_key')
            ->get();

        return $this->inertia('Sahodaya/SportsAgeGroups/Index', $this->programNavProps('sports-meet') + [
            'groups'           => $groups,
            'activeAcademicYear' => \App\Support\AcademicYear::activeRecord(),
            'globalAgeCutoffDate' => SahodayaProfile::where('tenant_id', $this->sahodaya->id)
                ->value('sports_age_cutoff_date'),
        ]);
    }

    public function updateGlobalCutoff(Request $request)
    {
        $data = $request->validate([
            'sports_age_cutoff_date' => 'nullable|date',
        ]);

        SahodayaProfile::where('tenant_id', $this->sahodaya->id)->update([
            'sports_age_cutoff_date' => $data['sports_age_cutoff_date'] ?? null,
        ]);

        return back()->with('success', $data['sports_age_cutoff_date'] ?? null
            ? 'Sahodaya-wide age reference date saved.'
            : 'Sahodaya-wide age reference date cleared — falls back to the computed default.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'group_key'   => [
                'required', 'string', 'max:20', 'regex:/^(open|u\d{1,2})$/',
                Rule::unique('fest_sports_age_group_configs', 'group_key')
                    ->where('tenant_id', $this->sahodaya->id),
            ],
            'label'       => 'required|string|max:120',
            'under_age'   => 'nullable|integer|min:1|max:99',
            'sort_order'  => 'nullable|integer|min:0|max:999',
            'default_fee' => 'nullable|numeric|min:0',
        ]);

        if ($data['group_key'] === 'open') {
            $data['under_age'] = null;
        } elseif (empty($data['under_age'])) {
            return back()->withErrors(['under_age' => 'Under age is required unless the group is Open.']);
        }

        FestSportsAgeGroupConfig::create([
            'tenant_id'   => $this->sahodaya->id,
            'group_key'   => strtolower($data['group_key']),
            'label'       => $data['label'],
            'under_age'   => $data['under_age'] ?? null,
            'sort_order'  => $data['sort_order'] ?? 100,
            'default_fee' => $data['default_fee'] ?? null,
            'is_active'   => true,
        ]);

        return back()->with('success', 'Age category added.');
    }

    public function update(Request $request, string $tenantId, FestSportsAgeGroupConfig $sportsAgeGroup)
    {
        abort_if($sportsAgeGroup->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'label'       => 'required|string|max:120',
            'under_age'   => 'nullable|integer|min:1|max:99',
            'sort_order'  => 'nullable|integer|min:0|max:999',
            'default_fee' => 'nullable|numeric|min:0',
            'is_active'   => 'nullable|boolean',
        ]);

        if ($sportsAgeGroup->group_key === 'open') {
            $data['under_age'] = null;
        } elseif (array_key_exists('under_age', $data) && empty($data['under_age'])) {
            return back()->withErrors(['under_age' => 'Under age is required for this category.']);
        }

        $data = PersistDefaults::coalesce($data, [
            'sort_order' => $sportsAgeGroup->sort_order ?? 100,
        ]);

        $sportsAgeGroup->update($data);

        return back()->with('success', 'Age category updated.');
    }

    public function destroy(string $tenantId, FestSportsAgeGroupConfig $sportsAgeGroup)
    {
        abort_if($sportsAgeGroup->tenant_id !== $this->sahodaya->id, 403);

        $inUse = FestEventItem::whereHas('event', fn ($q) => $q->where('tenant_id', $this->sahodaya->id))
            ->where('age_group', $sportsAgeGroup->group_key)
            ->exists();

        if ($inUse) {
            $sportsAgeGroup->update(['is_active' => false]);

            return back()->with('success', 'Category deactivated (in use by event items).');
        }

        $sportsAgeGroup->delete();

        return back()->with('success', 'Age category removed.');
    }

    public function resetDefaults(FestSportsAgeGroupRegistry $registry)
    {
        FestSportsAgeGroupConfig::where('tenant_id', $this->sahodaya->id)->delete();
        $registry->forTenant($this->sahodaya->id)->ensureDefaults();

        return back()->with('success', 'Age categories reset to system defaults.');
    }
}
