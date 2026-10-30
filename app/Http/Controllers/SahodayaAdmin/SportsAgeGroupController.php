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
    /**
     * Age categories live at the Sahodaya level (fest_sports_age_group_configs
     * is keyed only by tenant_id, not by program/event_type — it was never
     * actually sports-specific in the data model, only in where it was
     * reachable from). Rendered generically, like CompetitionTypeController,
     * so it shows the common Sahodaya sidebar rather than being locked to
     * the Sports Meet program's own nav — every program can reach and use
     * the same age bands.
     */
    public function index(FestSportsAgeGroupRegistry $registry)
    {
        $registry->forTenant($this->sahodaya->id)->ensureDefaults();

        $usage = FestEventItem::whereHas('event', fn ($q) => $q->where('tenant_id', $this->sahodaya->id))
            ->selectRaw('age_group, COUNT(*) as total')->groupBy('age_group')->pluck('total', 'age_group');
        $groups = FestSportsAgeGroupConfig::where('tenant_id', $this->sahodaya->id)
            ->orderBy('sort_order')
            ->orderBy('group_key')
            ->get()->each(fn ($group) => $group->setAttribute('items_count', (int) ($usage[$group->group_key] ?? 0)));

        return $this->inertia('Sahodaya/SportsAgeGroups/Index', [
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

        \App\Support\FestSportsAgeGroup::forgetTenantCache($this->sahodaya->id);

        return back()->with('success', $data['sports_age_cutoff_date'] ?? null
            ? 'Sahodaya-wide age reference date saved.'
            : 'Sahodaya-wide age reference date cleared — falls back to the computed default.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'group_key'   => [
                'required', 'string', 'max:20', 'regex:/^[a-z][a-z0-9_]{0,19}$/',
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

        \App\Support\FestSportsAgeGroup::forgetTenantCache($this->sahodaya->id);

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

        \App\Support\FestSportsAgeGroup::forgetTenantCache($this->sahodaya->id);

        return back()->with('success', 'Age category updated.');
    }

    public function destroy(string $tenantId, FestSportsAgeGroupConfig $sportsAgeGroup)
    {
        abort_if($sportsAgeGroup->tenant_id !== $this->sahodaya->id, 403);

        if ($this->categoryInUse($sportsAgeGroup->group_key)) {
            return back()->withErrors(['age_category' => 'This category is used by event items. Reassign those items before removing it.']);
        }

        $sportsAgeGroup->delete();

        \App\Support\FestSportsAgeGroup::forgetTenantCache($this->sahodaya->id);

        return back()->with('success', 'Age category removed.');
    }

    public function resetDefaults(FestSportsAgeGroupRegistry $registry)
    {
        $defaultKeys = array_keys(config('fest_co_curricular.sports.age_groups', []));
        $customKeys = FestSportsAgeGroupConfig::where('tenant_id', $this->sahodaya->id)->whereNotIn('group_key', $defaultKeys)->pluck('group_key');
        foreach ($customKeys as $key) {
            if ($this->categoryInUse($key)) {
                return back()->withErrors(['age_category' => 'Reset would remove a custom category used by event items. Reassign those items first.']);
            }
        }
        FestSportsAgeGroupConfig::where('tenant_id', $this->sahodaya->id)->delete();
        $registry->forTenant($this->sahodaya->id)->ensureDefaults();

        \App\Support\FestSportsAgeGroup::forgetTenantCache($this->sahodaya->id);

        return back()->with('success', 'Age categories reset to system defaults.');
    }
    private function categoryInUse(string $key): bool
    {
        return FestEventItem::where('age_group', $key)
            ->whereHas('event', fn ($q) => $q->where('tenant_id', $this->sahodaya->id))->exists();
    }

}
