<?php

namespace App\Http\Controllers\Traits;

use App\Models\SiteSection;
use App\Models\SiteSectionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase 7 — per-item CRUD for repeater fields stored in site_section_items.
 *
 * Both the school-admin BuilderApiController and the Sahodaya-admin
 * SiteBuilderApiController use this trait so each repeater item gets its own
 * row (with _enabled, _featured, _start_date, _end_date meta) instead of being
 * locked inside the section's config JSON blob.
 */
trait HandlesRepeaterItems
{
    // ── Public API ───────────────────────────────────────────────────────────

    public function listRepeaterItems(Request $request, string $tenantId, int $sectionId, string $itemKey): JsonResponse
    {
        $site = $this->resolveSite($request, $tenantId);
        $section = $this->sectionForSite($site, $sectionId);

        $this->abortIfNotRepeaterKey($section, $itemKey);

        $items = SiteSectionItem::query()
            ->where('tenant_id', $tenantId)
            ->where('site_id', $site->id)
            ->where('site_section_id', $sectionId)
            ->where('item_key', $itemKey)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['items' => $items]);
    }

    public function createRepeaterItem(Request $request, string $tenantId, int $sectionId, string $itemKey): JsonResponse
    {
        $site = $this->resolveSite($request, $tenantId);
        $section = $this->sectionForSite($site, $sectionId);

        $this->abortIfNotRepeaterKey($section, $itemKey);

        $validated = $request->validate([
            'data' => 'nullable|array',
            'meta' => 'nullable|array',
            'meta._enabled' => 'nullable|boolean',
            'meta._featured' => 'nullable|boolean',
            'meta._start_date' => 'nullable|date_format:Y-m-d',
            'meta._end_date' => 'nullable|date_format:Y-m-d',
            'is_enabled' => 'boolean',
            'is_featured' => 'boolean',
            'visible_from' => 'nullable|date',
            'visible_until' => 'nullable|date|after_or_equal:visible_from',
        ]);

        $maxSort = (int) SiteSectionItem::query()
            ->where('site_section_id', $sectionId)
            ->where('item_key', $itemKey)
            ->max('sort_order');

        $item = SiteSectionItem::create([
            'tenant_id' => $tenantId,
            'site_id' => $site->id,
            'site_section_id' => $sectionId,
            'item_key' => $itemKey,
            'sort_order' => $maxSort + 1,
            'display_order' => $maxSort + 1,
            'data' => $validated['data'] ?? [],
            'meta' => $validated['meta'] ?? [],
            'is_enabled' => $validated['is_enabled'] ?? true,
            'is_featured' => $validated['is_featured'] ?? false,
            'visible_from' => $this->resolveDate($validated, 'visible_from', '_start_date'),
            'visible_until' => $this->resolveDate($validated, 'visible_until', '_end_date'),
        ]);

        $this->syncConfigFromItems($section, $itemKey);

        return response()->json($item, 201);
    }

    public function updateRepeaterItem(Request $request, string $tenantId, int $sectionId, int $itemId): JsonResponse
    {
        $site = $this->resolveSite($request, $tenantId);
        $section = $this->sectionForSite($site, $sectionId);

        $item = SiteSectionItem::query()
            ->where('tenant_id', $tenantId)
            ->where('site_id', $site->id)
            ->where('site_section_id', $sectionId)
            ->findOrFail($itemId);

        $validated = $request->validate([
            'data' => 'nullable|array',
            'meta' => 'nullable|array',
            'is_enabled' => 'boolean',
            'is_featured' => 'boolean',
            'visible_from' => 'nullable|date',
            'visible_until' => 'nullable|date|after_or_equal:visible_from',
        ]);

        $updatable = array_intersect_key(
            $validated,
            array_flip(['data', 'meta', 'is_enabled', 'is_featured', 'visible_from', 'visible_until'])
        );

        if (isset($updatable['meta']['_enabled'])) {
            $updatable['is_enabled'] = (bool) $updatable['meta']['_enabled'];
        }
        if (isset($updatable['meta']['_featured'])) {
            $updatable['is_featured'] = (bool) $updatable['meta']['_featured'];
        }
        $updatable['visible_from'] = $this->resolveDate($updatable, 'visible_from', '_start_date');
        $updatable['visible_until'] = $this->resolveDate($updatable, 'visible_until', '_end_date');

        $item->update($updatable);
        $this->syncConfigFromItems($section, $item->item_key);

        return response()->json($item->fresh());
    }

    public function deleteRepeaterItem(Request $request, string $tenantId, int $sectionId, int $itemId): JsonResponse
    {
        $site = $this->resolveSite($request, $tenantId);
        $section = $this->sectionForSite($site, $sectionId);

        $item = SiteSectionItem::query()
            ->where('tenant_id', $tenantId)
            ->where('site_id', $site->id)
            ->where('site_section_id', $sectionId)
            ->findOrFail($itemId);

        $key = $item->item_key;
        $item->delete();
        $this->syncConfigFromItems($section, $key);

        return response()->json(['deleted' => true]);
    }

    public function toggleRepeaterItem(Request $request, string $tenantId, int $sectionId, int $itemId): JsonResponse
    {
        $site = $this->resolveSite($request, $tenantId);
        $section = $this->sectionForSite($site, $sectionId);

        $item = SiteSectionItem::query()
            ->where('tenant_id', $tenantId)
            ->where('site_id', $site->id)
            ->where('site_section_id', $sectionId)
            ->findOrFail($itemId);

        $item->update(['is_enabled' => ! $item->is_enabled]);
        $this->syncConfigFromItems($section, $item->item_key);

        return response()->json($item->fresh());
    }

    public function featureRepeaterItem(Request $request, string $tenantId, int $sectionId, int $itemId): JsonResponse
    {
        $site = $this->resolveSite($request, $tenantId);
        $section = $this->sectionForSite($site, $sectionId);

        $item = SiteSectionItem::query()
            ->where('tenant_id', $tenantId)
            ->where('site_id', $site->id)
            ->where('site_section_id', $sectionId)
            ->findOrFail($itemId);

        $item->update(['is_featured' => true]);
        $this->syncConfigFromItems($section, $item->item_key);

        return response()->json($item->fresh());
    }

    public function reorderRepeaterItems(Request $request, string $tenantId, int $sectionId, string $itemKey): JsonResponse
    {
        $site = $this->resolveSite($request, $tenantId);
        $section = $this->sectionForSite($site, $sectionId);

        $this->abortIfNotRepeaterKey($section, $itemKey);

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|distinct',
        ]);

        $validIds = SiteSectionItem::query()
            ->where('tenant_id', $tenantId)
            ->where('site_id', $site->id)
            ->where('site_section_id', $sectionId)
            ->where('item_key', $itemKey)
            ->whereIn('id', $validated['ids'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        abort_unless(count($validIds) === count($validated['ids']), 422, 'IDs must all belong to this repeater.');

        DB::transaction(function () use ($validIds) {
            foreach ($validIds as $order => $id) {
                SiteSectionItem::whereKey($id)->update(['sort_order' => $order, 'display_order' => $order]);
            }
        });

        $this->syncConfigFromItems($section, $itemKey);

        return response()->json(['reordered' => true]);
    }

    // ── Internal helpers (caller must provide) ───────────────────────────────

    /**
     * Override this in the consuming controller to resolve a WebsiteSite from the request.
     */
    abstract protected function resolveSite(Request $request, string $tenantId): \App\Models\WebsiteSite;

    /**
     * Override this to look up a section within a site (throws 404 if not found).
     */
    abstract protected function sectionForSite(\App\Models\WebsiteSite $site, int $sectionId): \App\Models\SiteSection;

    /**
     * Override this to bust cache for a tenant after writes.
     */
    abstract protected function bustCache(string $tenantId): void;

    // ── Private implementation ───────────────────────────────────────────────

    private function abortIfNotRepeaterKey(SiteSection $section, string $itemKey): void
    {
        if (! $this->isRepeaterKey($section, $itemKey)) {
            abort(404, "Repeater key '{$itemKey}' not found in section '{$section->section_type}/{$section->variant}'.");
        }
    }

    private function isRepeaterKey(SiteSection $section, string $itemKey): bool
    {
        $fields = \App\Support\SectionFieldRegistry::fields($section->section_type, $section->variant);

        return collect($fields)
            ->contains(fn ($f) => ($f['type'] ?? null) === 'repeater' && ($f['key'] ?? '') === $itemKey);
    }

    private function resolveDate(array $source, string $directKey, string $metaKey): ?\Illuminate\Support\Carbon
    {
        $value = $source[$directKey] ?? ($source['meta'][$metaKey] ?? null);

        return $value ? \Illuminate\Support\Carbon::parse($value) : null;
    }

    /**
     * Rebuild config JSON from site_section_items table (authoritative source)
     * then mark the section as draft so the admin knows to re-publish.
     */
    private function syncConfigFromItems(SiteSection $section, ?string $onlyKey = null): void
    {
        $fields = \App\Support\SectionFieldRegistry::fields($section->section_type, $section->variant);
        $repeaterKeys = collect($fields)
            ->filter(fn ($f) => ($f['type'] ?? null) === 'repeater')
            ->pluck('key');

        if ($onlyKey !== null) {
            $repeaterKeys = $repeaterKeys->filter(fn ($k) => $k === $onlyKey);
        }

        $config = $section->config ?? [];
        $itemsByKey = SiteSectionItem::query()
            ->where('site_section_id', $section->id)
            ->whereIn('item_key', $repeaterKeys)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('item_key');

        foreach ($repeaterKeys as $key) {
            $config[$key] = ($itemsByKey[$key] ?? collect())
                ->sortBy('sort_order')
                ->values()
                ->map(fn ($item) => array_merge($item->data ?? [], $item->meta ?? []))
                ->all();
        }

        $section->update([
            'config' => $config,
            'status' => \App\Models\SiteSection::STATUS_DRAFT,
        ]);
        $this->bustCache($section->tenant_id);
    }
}
