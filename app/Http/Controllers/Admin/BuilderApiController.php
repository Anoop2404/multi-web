<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSection;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Support\SectionFieldRegistry;
use App\Support\SectionVariantResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuilderApiController extends Controller
{
    // ── Sections ─────────────────────────────────────────────────────────────

    public function sections(string $tenantId): JsonResponse
    {
        $sections = SiteSection::where('tenant_id', $tenantId)
            ->orderBy('display_order')
            ->get();

        return response()->json($sections);
    }

    public function storeSection(Request $request, string $tenantId): JsonResponse
    {
        $data = $request->validate([
            'section_type' => 'required|string|max:50',
            'variant'      => 'required|string|max:50',
            'config'       => 'nullable|array',
            'is_active'    => 'boolean',
        ]);

        $data['tenant_id']     = $tenantId;
        $data['display_order'] = SiteSection::where('tenant_id', $tenantId)->max('display_order') + 1;

        $section = SiteSection::create($data);
        $this->bustCache($tenantId);

        return response()->json($section, 201);
    }

    public function updateSection(Request $request, string $tenantId, int $sectionId): JsonResponse
    {
        $section = SiteSection::where('tenant_id', $tenantId)->findOrFail($sectionId);

        $data = $request->validate([
            'section_type' => 'string|max:50',
            'variant'      => 'string|max:50',
            'config'       => 'nullable|array',
            'is_active'    => 'boolean',
        ]);

        // Archive current config when variant changes
        $newVariant = $data['variant'] ?? $section->variant;
        if ($newVariant !== $section->variant) {
            $section->archiveCurrentConfig();
            $data['archived_configs'] = $section->archived_configs;
            // Reset config for the new variant so editors start fresh
            if (!isset($data['config']) || empty($data['config'])) {
                $data['config'] = [];
            }
        }

        $section->update($data);
        $this->bustCache($tenantId);

        return response()->json($section);
    }

    public function deleteSection(string $tenantId, int $sectionId): JsonResponse
    {
        SiteSection::where('tenant_id', $tenantId)->findOrFail($sectionId)->delete();
        $this->bustCache($tenantId);

        return response()->json(['deleted' => true]);
    }

    public function toggleSection(string $tenantId, int $sectionId): JsonResponse
    {
        $section = SiteSection::where('tenant_id', $tenantId)->findOrFail($sectionId);
        $section->update(['is_active' => !$section->is_active]);
        $this->bustCache($tenantId);

        return response()->json($section);
    }

    public function reorderSections(Request $request, string $tenantId): JsonResponse
    {
        $ids = $request->validate(['ids' => 'required|array'])['ids'];

        foreach ($ids as $order => $id) {
            SiteSection::where('tenant_id', $tenantId)->where('id', $id)
                ->update(['display_order' => $order]);
        }

        $this->bustCache($tenantId);

        return response()->json(['reordered' => true]);
    }

    // ── Settings ──────────────────────────────────────────────────────────────

    public function getSetting(string $tenantId, string $key): JsonResponse
    {
        $setting = TenantSetting::where('tenant_id', $tenantId)->where('key', $key)->first();
        return response()->json([$key => $setting?->value ?? []]);
    }

    public function saveSetting(Request $request, string $tenantId): JsonResponse
    {
        $data = $request->validate([
            'key'   => 'required|string|max:100',
            'value' => 'required',
        ]);

        TenantSetting::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => $data['key']],
            ['value' => $data['value']]
        );

        Cache::forget("tenant:{$tenantId}:setting:{$data['key']}");
        $this->bustCache($tenantId);

        return response()->json(['saved' => true]);
    }

    // ── Nav config ────────────────────────────────────────────────────────────

    public function getNav(string $tenantId): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);
        return response()->json($tenant->getSetting('nav_config', []));
    }

    public function saveNav(Request $request, string $tenantId): JsonResponse
    {
        $data = $request->validate([
            'style' => 'nullable|string|max:50',
            'layout_variant' => 'nullable|string|max:50',
            'items' => 'nullable|array',
            'items.*.label' => 'required|string|max:100',
            'items.*.url'   => 'required|string|max:500',
            'items.*.children' => 'nullable|array',
        ]);

        $variant = $data['layout_variant'] ?? $data['style'] ?? 'logo-left';
        $data['style'] = $variant;
        $data['layout_variant'] = $variant;

        $tenant = Tenant::findOrFail($tenantId);
        $tenant->setSetting('nav_config', $data);

        return response()->json(['saved' => true]);
    }

    // ── Footer config ─────────────────────────────────────────────────────────

    public function getFooter(string $tenantId): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);
        return response()->json($tenant->getSetting('footer_config', []));
    }

    public function saveFooter(Request $request, string $tenantId): JsonResponse
    {
        $data = $request->validate([
            'style'            => 'nullable|string|max:50',
            'layout_variant'   => 'nullable|string|max:50',
            'tagline'          => 'nullable|string|max:500',
            'copyright'        => 'nullable|string|max:500',
            'address'          => 'nullable|string|max:500',
            'phone'            => 'nullable|string|max:50',
            'email'            => 'nullable|email|max:255',
            'quick_links'      => 'nullable|array',
            'quick_links.*.label' => 'required|string|max:100',
            'quick_links.*.url'   => 'required|string|max:500',
            'social_links'     => 'nullable|array',
            'sahodaya_link'    => 'nullable|array',
        ]);

        $variant = SectionVariantResolver::resolveFooterVariant($data);
        $data['style'] = $variant;
        $data['layout_variant'] = $variant;

        $tenant = Tenant::findOrFail($tenantId);
        $tenant->setSetting('footer_config', $data);

        return response()->json(['saved' => true]);
    }

    // ── Theme config ──────────────────────────────────────────────────────────

    public function getTheme(string $tenantId): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);
        return response()->json($tenant->getSetting('theme', []));
    }

    public function saveTheme(Request $request, string $tenantId): JsonResponse
    {
        $data = $request->validate([
            'primary'       => 'nullable|string|max:20',
            'secondary'     => 'nullable|string|max:20',
            'accent_color'  => 'nullable|string|max:20',
            'font_heading'  => 'nullable|string|max:100',
            'font_body'     => 'nullable|string|max:100',
            'border_radius' => 'nullable|string|max:20',
            'navbar_style'  => 'nullable|string|max:50',
            'footer_style'  => 'nullable|string|max:50',
        ]);

        $tenant = Tenant::findOrFail($tenantId);
        $tenant->setSetting('theme', $data);

        return response()->json(['saved' => true]);
    }

    // ── Widgets config ────────────────────────────────────────────────────────

    public function getWidgets(string $tenantId): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);
        return response()->json($tenant->getSetting('widgets', []));
    }

    public function saveWidgets(Request $request, string $tenantId): JsonResponse
    {
        $data = $request->validate([
            'whatsapp_enabled'        => 'nullable|boolean',
            'whatsapp_number'         => 'nullable|string|max:50',
            'topbar'                  => 'nullable|array',
            'admission_banner'        => 'nullable|array',
            'news_ticker'             => 'nullable|array',
            'ticker'                  => 'nullable|array',
            'social_links'            => 'nullable|array',
            'visitor_counter'         => 'nullable|array',
            'social_strip'            => 'nullable|array',
            'cbse_badge_show'         => 'nullable|boolean',
            'cbse_affiliation_number' => 'nullable|string|max:100',
            'cbse_affiliation_no'     => 'nullable|string|max:100',
        ]);

        $tenant = Tenant::findOrFail($tenantId);
        $existing = $tenant->getSetting('widgets', []) ?? [];
        $tenant->setSetting('widgets', array_merge($existing, $data));

        return response()->json(['saved' => true]);
    }

    // ── Section field definitions ─────────────────────────────────────────────

    public function sectionDefinitions(): JsonResponse
    {
        return response()->json(SectionFieldRegistry::all());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function bustCache(string $tenantId): void
    {
        if (Cache::supportsTags()) {
            Cache::tags(["tenant:{$tenantId}"])->flush();
        }
    }
}
