<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSection;
use App\Models\SiteSectionVersion;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\WebsiteSite;
use App\Support\HtmlSanitizer;
use App\Support\SectionFieldRegistry;
use App\Support\SectionVariantResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuilderApiController extends Controller
{
    // ── Sections ─────────────────────────────────────────────────────────────

    public function sections(Request $request, string $tenantId): JsonResponse
    {
        $site = $this->resolveSite($request, $tenantId);

        $sections = $site->sectionQuery()
            ->orderBy('display_order')
            ->get()
            ->map(fn (SiteSection $s) => $this->sectionPayload($s));

        return response()->json($sections);
    }

    public function storeSection(Request $request, string $tenantId): JsonResponse
    {
        $data = $request->validate([
            'section_type' => 'required|string|max:50',
            'variant' => 'required|string|max:50',
            'config' => 'nullable|array',
            'is_active' => 'boolean',
            'site_id' => 'nullable|integer',
            'layout_json' => 'nullable|array',
            'layout_json.width' => 'nullable|in:narrow,standard,wide,full',
            'layout_json.spacing' => 'nullable|in:compact,standard,spacious',
            'layout_json.surface' => 'nullable|in:canvas,muted,primary,dark,image',
            'layout_json.heading_alignment' => 'nullable|in:left,center',
            'layout_json.media_treatment' => 'nullable|in:natural,framed,editorial,edge-to-edge',
            'status' => 'nullable|in:draft,published',
        ]);

        $site = WebsiteSite::resolveForTenant(
            $tenantId,
            isset($data['site_id']) ? (int) $data['site_id'] : null,
        );
        $data['tenant_id'] = $tenantId;
        $data['site_id'] = $site->id;
        $data['display_order'] = ((int) $site->sectionQuery()->max('display_order')) + 1;
        $data['config'] = HtmlSanitizer::sanitizeConfig($data['config'] ?? []);
        $data['status'] = $data['status'] ?? SiteSection::STATUS_DRAFT;
        $data['updated_by'] = auth()->id();

        if ($data['status'] === SiteSection::STATUS_PUBLISHED) {
            $data['published_config'] = $data['config'];
            $data['published_at'] = now();
        }

        $section = SiteSection::create($data);
        $section->recordVersion('Created');
        $this->bustCache($tenantId);

        return response()->json($this->sectionPayload($section), 201);
    }

    public function updateSection(Request $request, string $tenantId, int $sectionId): JsonResponse
    {
        $site = $this->resolveSite($request, $tenantId);
        $section = $this->sectionForSite($site, $sectionId);

        $data = $request->validate([
            'section_type' => 'string|max:50',
            'variant' => 'string|max:50',
            'config' => 'nullable|array',
            'is_active' => 'boolean',
            'status' => 'nullable|in:draft,published',
            'site_id' => 'nullable|integer',
            'layout_json' => 'nullable|array',
            'layout_json.width' => 'nullable|in:narrow,standard,wide,full',
            'layout_json.spacing' => 'nullable|in:compact,standard,spacious',
            'layout_json.surface' => 'nullable|in:canvas,muted,primary,dark,image',
            'layout_json.heading_alignment' => 'nullable|in:left,center',
            'layout_json.media_treatment' => 'nullable|in:natural,framed,editorial,edge-to-edge',
        ]);

        // site_id selects the builder scope. Moving a section between sites is
        // intentionally not supported by this endpoint.
        unset($data['site_id']);

        if (array_key_exists('config', $data) && is_array($data['config'])) {
            $data['config'] = HtmlSanitizer::sanitizeConfig($data['config']);
        }

        $newVariant = $data['variant'] ?? $section->variant;
        if ($newVariant !== $section->variant) {
            $section->archiveCurrentConfig();
            $data['archived_configs'] = $section->archived_configs;
            if (! isset($data['config']) || empty($data['config'])) {
                $data['config'] = [];
            }
        }

        // Saving edits keeps draft until publish (unless explicitly publishing)
        if (($data['status'] ?? null) !== SiteSection::STATUS_PUBLISHED) {
            $data['status'] = SiteSection::STATUS_DRAFT;
        }

        $data['updated_by'] = auth()->id();
        $section->fill($data);
        $section->save();
        $section->recordVersion('Updated');

        if (($data['status'] ?? null) === SiteSection::STATUS_PUBLISHED) {
            $section->publish();
        }

        $this->bustCache($tenantId);

        return response()->json($this->sectionPayload($section->fresh()));
    }

    public function publishSection(Request $request, string $tenantId, int $sectionId): JsonResponse
    {
        $site = $this->resolveSite($request, $tenantId);
        $section = $this->sectionForSite($site, $sectionId);
        $section->config = HtmlSanitizer::sanitizeConfig($section->config ?? []);
        $section->publish();
        $this->bustCache($tenantId);

        return response()->json($this->sectionPayload($section->fresh()));
    }

    public function sectionVersions(Request $request, string $tenantId, int $sectionId): JsonResponse
    {
        $site = $this->resolveSite($request, $tenantId);
        $section = $this->sectionForSite($site, $sectionId);

        return response()->json(
            $section->versions()->limit(30)->get(['id', 'variant', 'note', 'created_by', 'created_at'])
        );
    }

    public function restoreSectionVersion(Request $request, string $tenantId, int $sectionId, int $versionId): JsonResponse
    {
        $site = $this->resolveSite($request, $tenantId);
        $section = $this->sectionForSite($site, $sectionId);
        $version = SiteSectionVersion::where('site_section_id', $section->id)->findOrFail($versionId);

        $section->recordVersion('Before restore #'.$versionId);
        $section->update([
            'variant' => $version->variant ?: $section->variant,
            'config' => HtmlSanitizer::sanitizeConfig($version->config ?? []),
            'layout_json' => $version->layout_json ?? [],
            'status' => SiteSection::STATUS_DRAFT,
            'updated_by' => auth()->id(),
        ]);
        $this->bustCache($tenantId);

        return response()->json($this->sectionPayload($section->fresh()));
    }

    public function deleteSection(Request $request, string $tenantId, int $sectionId): JsonResponse
    {
        $site = $this->resolveSite($request, $tenantId);
        $this->sectionForSite($site, $sectionId)->delete();
        $this->bustCache($tenantId);

        return response()->json(['deleted' => true]);
    }

    public function toggleSection(Request $request, string $tenantId, int $sectionId): JsonResponse
    {
        $site = $this->resolveSite($request, $tenantId);
        $section = $this->sectionForSite($site, $sectionId);
        $section->update(['is_active' => ! $section->is_active]);
        $this->bustCache($tenantId);

        return response()->json($this->sectionPayload($section->fresh()));
    }

    public function reorderSections(Request $request, string $tenantId): JsonResponse
    {
        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|distinct',
            'site_id' => 'nullable|integer',
        ]);
        $site = $this->resolveSite($request, $tenantId);
        $ids = array_map('intval', $data['ids']);

        $allowedIds = $site->sectionQuery()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        abort_unless(count($allowedIds) === count($ids), 422, 'All sections must belong to the selected website.');

        DB::transaction(function () use ($site, $ids) {
            foreach ($ids as $order => $id) {
                $site->sectionQuery()->whereKey($id)->update(['display_order' => $order]);
            }
        });

        $this->bustCache($tenantId);

        return response()->json(['reordered' => true]);
    }

    /** @return array<string, mixed> */
    private function sectionPayload(SiteSection $section): array
    {
        return array_merge($section->toArray(), [
            'has_unpublished_changes' => $section->hasUnpublishedChanges(),
        ]);
    }

    private function resolveSite(Request $request, string $tenantId): WebsiteSite
    {
        $siteId = $request->filled('site_id') ? $request->integer('site_id') : null;

        return WebsiteSite::resolveForTenant($tenantId, $siteId ?: null);
    }

    private function sectionForSite(WebsiteSite $site, int $sectionId): SiteSection
    {
        return $site->sectionQuery()->findOrFail($sectionId);
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
        // Merge rather than overwrite — a raw overwrite drops keys this request
        // doesn't touch, including the `customized` flag the Sahodaya self-service
        // theme path (SahodayaTenantBranding::saveTheme()) relies on to know a theme
        // was deliberately chosen rather than left at its subdomain default.
        $existing = $tenant->getSetting('theme', []) ?? [];
        $tenant->setSetting('theme', array_merge($existing, $data, ['customized' => true]));

        return response()->json(['saved' => true]);
    }

    // ── V2 experience design tokens (any tenant's WebsiteSite.design_json) ─────

    public function getDesign(string $tenantId): JsonResponse
    {
        $site = WebsiteSite::where('tenant_id', $tenantId)->where('is_primary', true)->first();

        return response()->json([
            'experience_version' => $site?->experience_version ?? 'v1',
            'design' => $site?->design_json ?? [],
        ]);
    }

    public function saveDesign(Request $request, string $tenantId): JsonResponse
    {
        $site = WebsiteSite::where('tenant_id', $tenantId)->where('is_primary', true)->firstOrFail();

        $data = $request->validate([
            'primary' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'accent_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'display_font' => 'required|in:Inter,Manrope,Merriweather,Roboto',
            'body_font' => 'required|in:Inter,Manrope,Roboto',
            'type_scale' => 'required|in:compact,balanced,editorial',
            'density' => 'required|in:compact,comfortable,spacious',
            'surface' => 'required|in:flat,bordered,soft,elevated',
            'corners' => 'required|in:square,soft,rounded',
            'buttons' => 'required|in:solid,bordered,understated',
            'images' => 'required|in:documentary,vibrant,formal,monochrome',
            'motion' => 'required|in:none,restrained,expressive',
        ]);

        $site->update(['design_json' => $data]);
        $this->bustCache($tenantId);

        return response()->json(['saved' => true, 'design' => $data]);
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
