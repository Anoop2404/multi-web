<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestCompetitionType;
use App\Services\Events\FestCompetitionTypeRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FestCompetitionTypeController extends SahodayaAdminController
{
    public function index(FestCompetitionTypeRegistry $registry)
    {
        $registry->forTenant($this->sahodaya->id)->ensureDefaults();

        return $this->inertia('Sahodaya/CompetitionTypes/Index', [
            'types' => $registry->forTenant($this->sahodaya->id)->rows(),
            'taxonomyMastersUrl' => "/sahodaya-admin/{$this->sahodaya->id}/taxonomy-masters",
        ]);
    }

    public function store(Request $request, FestCompetitionTypeRegistry $registry)
    {
        $registry->forTenant($this->sahodaya->id)->ensureDefaults();

        $data = $request->validate([
            'type_key' => [
                'required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('fest_competition_types', 'type_key')->where('tenant_id', $this->sahodaya->id),
            ],
            'label' => 'required|string|max:120',
            'description' => 'nullable|string|max:255',
            'nav_slug' => 'nullable|string|max:60|regex:/^[a-z0-9\-]+$/',
            'icon' => 'nullable|string|max:40',
            'is_singleton' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:999',
        ]);

        $typeKey = strtolower($data['type_key']);
        $navSlug = $data['nav_slug'] ?? Str::slug(str_replace('_', '-', $typeKey));

        FestCompetitionType::create([
            'tenant_id' => $this->sahodaya->id,
            'type_key' => $typeKey,
            'label' => $data['label'],
            'nav_slug' => $navSlug,
            'route_prefix' => $navSlug,
            'icon' => $data['icon'] ?? 'calendar',
            'description' => $data['description'] ?? null,
            'is_singleton' => (bool) ($data['is_singleton'] ?? false),
            'is_system' => false,
            'sort_order' => $data['sort_order'] ?? 200,
            'is_active' => true,
        ]);

        $registry->forTenant($this->sahodaya->id)->ensureDefaultCatalogSection($typeKey);

        $hubUrl = "/sahodaya-admin/{$this->sahodaya->id}/programs/{$navSlug}";

        return back()->with('success', "Competition type \"{$data['label']}\" ready — open {$hubUrl} to add events and catalog items.");
    }

    public function update(Request $request, string $tenantId, FestCompetitionType $competitionType)
    {
        abort_if($competitionType->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'label' => 'required|string|max:120',
            'description' => 'nullable|string|max:255',
            'nav_slug' => 'nullable|string|max:60|regex:/^[a-z0-9\-]+$/',
            'icon' => 'nullable|string|max:40',
            'is_singleton' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:999',
            'is_active' => 'nullable|boolean',
        ]);

        // System types keep type_key / is_system; admins may still retitle and toggle active.
        $competitionType->update([
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
            'nav_slug' => $data['nav_slug'] ?? $competitionType->nav_slug,
            'icon' => $data['icon'] ?? $competitionType->icon,
            'is_singleton' => $competitionType->is_system
                ? $competitionType->is_singleton
                : (bool) ($data['is_singleton'] ?? $competitionType->is_singleton),
            'sort_order' => $data['sort_order'] ?? $competitionType->sort_order,
            'is_active' => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : $competitionType->is_active,
        ]);

        return back()->with('success', 'Competition type updated.');
    }

    public function destroy(string $tenantId, FestCompetitionType $competitionType, FestCompetitionTypeRegistry $registry)
    {
        abort_if($competitionType->tenant_id !== $this->sahodaya->id, 403);

        if ($competitionType->is_system) {
            return back()->with('error', 'System competition types cannot be deleted. Deactivate them instead.');
        }

        if ($registry->forTenant($this->sahodaya->id)->typeInUse($competitionType->type_key)) {
            $competitionType->update(['is_active' => false]);

            return back()->with('success', 'Competition type deactivated (events already use this key).');
        }

        $competitionType->delete();

        return back()->with('success', 'Competition type removed.');
    }

    public function resetDefaults(FestCompetitionTypeRegistry $registry)
    {
        FestCompetitionType::where('tenant_id', $this->sahodaya->id)
            ->where('is_system', true)
            ->delete();

        $registry->forTenant($this->sahodaya->id)->ensureDefaults();

        return back()->with('success', 'System competition types reset to defaults. Custom types were kept.');
    }
}
