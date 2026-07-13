<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestCatalogItem;
use App\Models\FestEvent;
use App\Models\FestItemHead;
use App\Services\Events\FestCatalogService;
use App\Services\Events\FestIdCardService;
use App\Services\Events\FestItemCatalogService;
use App\Services\Events\FestItemHeadService;
use App\Services\Events\FestTaxonomyRegistry;
use App\Support\FestPageActivity;
use App\Support\FestCatalogSections;
use App\Support\ProgramRouteMap;
use App\Services\Audit\PlatformAuditLogger;
use App\Support\FestSportsAgeGroup;
use App\Support\FestTeamSquadRules;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FestCatalogController extends SahodayaAdminController
{
    public function index(Request $request, string $tenantId, string $program, FestCatalogService $catalogService)
    {
        $ctx = $this->catalogContext($program, $catalogService);

        return $this->inertia('Sahodaya/Catalog/Hub', $ctx + [
            'events'       => $this->programEvents($ctx['meta']['eventType']),
            'activityLogs' => $this->catalogActivityLogs(FestPageActivity::CATALOG_HUB),
        ]);
    }

    public function master(Request $request, string $tenantId, FestCatalogService $catalogService)
    {
        $program = $this->catalogProgramSlug($request);
        $section = $request->route('section');
        $ctx = $this->catalogContext($program, $catalogService);
        $sectionMeta = $this->resolveSection($ctx['meta']['eventType'], $section);
        $items = $this->buildItemQuery($ctx['meta']['eventType'], $sectionMeta, $request)->get();

        return $this->inertia('Sahodaya/Catalog/Master', $ctx + [
            'section'      => $sectionMeta,
            'items'        => $items,
            'filters'      => $request->only(['enabled', 'age_group', 'gender', 'venue_type', 'sport_discipline', 'participant_type', 'class_group', 'head_key', 'q']),
            'groupedItems' => app(FestItemCatalogService::class)->groupForDisplay($items, $ctx['meta']['eventType']),
            'itemHeads'      => $this->catalogItemHeadOptions($ctx['meta']['eventType']),
            'events'       => $this->programEvents($ctx['meta']['eventType']),
            'activityLogs' => $this->catalogActivityLogs(FestPageActivity::CATALOG_MASTER),
        ]);
    }

    public function heads(string $tenantId, string $program, FestCatalogService $catalogService, FestItemHeadService $headService)
    {
        $ctx = $this->catalogContext($program, $catalogService);
        abort_unless($ctx['meta']['eventType'] === 'sports', 404);

        $headService->ensureCatalogHeads($this->sahodaya->id, 'sports');
        $headService->syncCatalogItemHeadKeys($this->sahodaya->id, 'sports');

        $heads = FestItemHead::forTenant($this->sahodaya->id)
            ->whereNull('event_id')
            ->orderBy('sort_order')
            ->get();

        $itemsByHead = FestCatalogItem::forProgram($this->sahodaya->id, 'sports')
            ->orderBy('display_order')
            ->orderBy('title')
            ->get(['id', 'title', 'head_key', 'is_enabled'])
            ->groupBy('head_key');

        $headsPayload = $heads->map(fn (FestItemHead $head) => [
            'id' => $head->id,
            'name' => $head->name,
            'catalog_key' => $head->catalog_key,
            'sport_discipline' => $head->sport_discipline,
            'is_team_heading' => $head->is_team_heading,
            'items' => ($itemsByHead->get($head->catalog_key) ?? collect())->values(),
        ])->values();

        $unassigned = ($itemsByHead->get('') ?? collect())
            ->merge($itemsByHead->get(null) ?? collect())
            ->values();

        return $this->inertia('Sahodaya/Catalog/Heads', $ctx + [
            'heads' => $headsPayload,
            'unassignedItems' => $unassigned,
            'disciplines' => app(FestTaxonomyRegistry::class)
                ->forTenant($this->sahodaya->id)->labels('sport_discipline'),
            'events'       => $this->programEvents($ctx['meta']['eventType']),
            'activityLogs' => $this->catalogActivityLogs(FestPageActivity::CATALOG_MASTER),
        ]);
    }

    public function storeHead(Request $request, string $tenantId, string $program, PlatformAuditLogger $audit)
    {
        $meta = $this->programMeta($program);
        abort_unless($meta['eventType'] === 'sports', 404);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'sport_discipline' => 'nullable|string|max:60',
            'is_team_heading' => 'nullable|boolean',
        ]);

        $catalogKey = Str::slug($data['name'], '_');
        abort_if(
            FestItemHead::forTenant($this->sahodaya->id)->whereNull('event_id')->where('catalog_key', $catalogKey)->exists(),
            422,
            'An item head with this name already exists.',
        );

        $order = (int) FestItemHead::forTenant($this->sahodaya->id)->whereNull('event_id')->max('sort_order') + 1;

        FestItemHead::create([
            'tenant_id' => $this->sahodaya->id,
            'event_type' => 'sports',
            'catalog_key' => $catalogKey,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'sport_discipline' => $data['sport_discipline'] ?? null,
            'is_team_heading' => (bool) ($data['is_team_heading'] ?? true),
            'sort_order' => $order,
        ]);

        $audit->festCatalog($this->sahodaya->id, $program, FestPageActivity::CATALOG_MASTER, 'catalog.head.created', "Item head added: {$data['name']}");

        return back()->with('success', 'Item head added to master catalog.');
    }

    public function previewHeadIdCard(string $tenantId, string $program, FestItemHead $head, FestIdCardService $idCards)
    {
        $meta = $this->programMeta($program);
        abort_unless($meta['eventType'] === 'sports', 404);
        abort_if((int) $head->tenant_id !== (int) $this->sahodaya->id, 403);
        abort_if($head->event_id !== null, 404, 'Sample ID cards are configured per catalog item head, not per event.');

        $itemTitles = FestCatalogItem::forProgram($this->sahodaya->id, 'sports')
            ->where('head_key', $head->catalog_key)
            ->orderBy('display_order')
            ->orderBy('title')
            ->limit(6)
            ->pluck('title')
            ->all();

        if ($itemTitles === []) {
            $itemTitles = ['Sample Item A', 'Sample Item B'];
        }

        $card = $idCards->sampleHeadCard($this->sahodaya, $head, $itemTitles);

        return view('fest.id-cards.premium-sheet', [
            'cards'          => [$card],
            'sections'       => null,
            'clusterName'    => $this->sahodaya->name,
            'clusterLogoSrc' => \App\Support\TenantBranding::logoEmbedSrc($this->sahodaya),
            'eventTitle'     => $head->name.' — Sample head ID card',
            'audience'       => 'student',
            'showTitle'      => true,
            'isSample'       => true,
        ]);
    }

    public function syncHeads(string $tenantId, string $program, FestItemHeadService $headService, PlatformAuditLogger $audit)
    {
        $meta = $this->programMeta($program);
        abort_unless($meta['eventType'] === 'sports', 404);

        $headsCreated = $headService->ensureCatalogHeads($this->sahodaya->id, 'sports');
        $headLinks = $headService->syncCatalogItemHeadKeys($this->sahodaya->id, 'sports');

        $audit->festCatalog($this->sahodaya->id, $program, FestPageActivity::CATALOG_MASTER, 'catalog.heads.synced', 'Master catalog item heads synced');

        $flash = match (true) {
            $headsCreated > 0 && $headLinks > 0 => "Created {$headsCreated} head(s) and linked {$headLinks} catalog item(s).",
            $headsCreated > 0 => "Created {$headsCreated} item head(s).",
            $headLinks > 0 => "Linked {$headLinks} catalog item(s) to main heads.",
            default => 'Item heads are already up to date.',
        };

        return back()->with('success', $flash);
    }

    public function list(Request $request, string $tenantId, FestCatalogService $catalogService)
    {
        $program = $this->catalogProgramSlug($request);
        $section = $request->route('section');
        $ctx = $this->catalogContext($program, $catalogService);
        $sectionMeta = $this->resolveSection($ctx['meta']['eventType'], $section);
        $items = $this->buildItemQuery($ctx['meta']['eventType'], $sectionMeta, $request)->get();

        return $this->inertia('Sahodaya/Catalog/List', $ctx + [
            'section'      => $sectionMeta,
            'items'        => $items,
            'filters'      => $request->only(['enabled', 'q']),
            'groupedItems' => app(FestItemCatalogService::class)->groupForDisplay($items, $ctx['meta']['eventType']),
            'itemHeads'      => $this->catalogItemHeadOptions($ctx['meta']['eventType']),
            'events'       => $this->programEvents($ctx['meta']['eventType']),
            'activityLogs' => $this->catalogActivityLogs(FestPageActivity::CATALOG_LIST),
        ]);
    }

    public function assign(Request $request, string $tenantId, string $program, FestCatalogService $catalogService)
    {
        $ctx = $this->catalogContext($program, $catalogService);
        $events = FestEvent::forTenant($this->sahodaya->id)
            ->ofType($ctx['meta']['eventType'])
            ->withCount('items')
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'status']);

        return $this->inertia('Sahodaya/Catalog/Assign', $ctx + [
            'events'       => $events,
            'activityLogs' => $this->catalogActivityLogs(FestPageActivity::CATALOG_ASSIGN),
        ]);
    }

    /** @deprecated Redirect old browse URLs */
    /** @deprecated Redirect old browse URLs */
    public function section(Request $request, string $tenantId)
    {
        $program = $this->catalogProgramSlug($request);
        $section = (string) $request->route('section');

        return redirect(ProgramRouteMap::sahodayaCatalogBase($tenantId, $program)."/master/{$section}");
    }

    /** Redirect legacy `/catalog/{section}` URLs missing the `/master` segment. */
    public function redirectLegacySection(Request $request, string $tenantId)
    {
        $program = $this->catalogProgramSlug($request);
        $section = (string) $request->route('section');
        $meta = $this->programMeta($program);
        abort_unless(FestCatalogSections::find($meta['eventType'], $section, $this->sahodaya->id) !== null, 404);

        return redirect(ProgramRouteMap::sahodayaCatalogBase($tenantId, $program)."/master/{$section}");
    }

    public function seed(Request $request, string $tenantId, FestCatalogService $catalogService, PlatformAuditLogger $audit)
    {
        $program = $this->catalogProgramSlug($request);
        $meta = $this->programMeta($program);
        $sync = $catalogService->ensureSeeded($this->sahodaya->id, $meta['eventType']);
        $created = $sync['created'];
        $updated = $sync['updated'];
        $headLinks = $sync['head_links'] ?? 0;
        $eventsSynced = 0;

        if ($meta['eventType'] === 'sports' && $request->boolean('link_events', true)) {
            FestEvent::forTenant($this->sahodaya->id)
                ->ofType('sports')
                ->each(function (FestEvent $event) use (&$eventsSynced) {
                    app(FestItemHeadService::class)->syncEventHeads($event);
                    $eventsSynced++;
                });
        }

        $auditMessage = match (true) {
            $created > 0 && $updated > 0 => "Synced catalog: {$created} added, {$updated} updated",
            $created > 0 => "Synced {$created} CKSC item(s)",
            $updated > 0 => "Updated {$updated} CKSC item(s)",
            $headLinks > 0 => "Linked {$headLinks} item(s) to main heads (Chess, Athletics, …)",
            $eventsSynced > 0 => "Relinked item heads on {$eventsSynced} sports event(s)",
            default => 'Catalog sync — already up to date',
        };

        $audit->festCatalog($this->sahodaya->id, $program, FestPageActivity::CATALOG_MASTER, 'catalog.seed', $auditMessage);

        $flash = match (true) {
            $created > 0 && $updated > 0 => "{$created} item(s) added and {$updated} updated from CKSC master.",
            $created > 0 => "{$created} CKSC item(s) added to master catalog.",
            $updated > 0 => "{$updated} CKSC item(s) updated from master catalog.",
            $headLinks > 0 => "Linked {$headLinks} catalog item(s) under main heads (Chess, Carrom, Athletics, …).",
            $eventsSynced > 0 => "Relinked item heads on {$eventsSynced} sports event(s).",
            default => 'Master catalog is already up to date.',
        };

        if ($eventsSynced > 0 && ! str_contains($flash, 'Relinked')) {
            $flash .= " Relinked heads on {$eventsSynced} sports event(s).";
        }

        return back()->with('success', $flash);
    }

    public function store(Request $request, string $tenantId, string $program, FestCatalogService $catalogService, PlatformAuditLogger $audit)
    {
        $meta = $this->programMeta($program);
        $data = $this->validateItem($request, $meta['eventType']);
        $row = $catalogService->normalizeRow($data);
        $order = (int) FestCatalogItem::forProgram($this->sahodaya->id, $meta['eventType'])->max('display_order');

        FestCatalogItem::create(array_merge($row, [
            'tenant_id'     => $this->sahodaya->id,
            'event_type'    => $meta['eventType'],
            'catalog_key'   => 'custom:'.$catalogService->catalogKey($row).':'.now()->timestamp,
            'source'        => 'custom',
            'is_enabled'    => true,
            'fee_enabled'   => filled($data['fee_amount'] ?? null),
            'display_order' => $order + 1,
        ]));

        $audit->festCatalog($this->sahodaya->id, $program, FestPageActivity::CATALOG_MASTER, 'catalog.item.created', "Custom item added: {$data['title']}");

        return back()->with('success', 'Custom item added to master catalog.');
    }

    public function update(Request $request, string $tenantId, FestCatalogItem $item, FestCatalogService $catalogService, PlatformAuditLogger $audit)
    {
        $program = $this->catalogProgramSlug($request);
        $meta = $this->programMeta($program);
        abort_if($item->tenant_id !== $this->sahodaya->id || $item->event_type !== $meta['eventType'], 403);

        $data = $request->validate([
            'is_enabled'   => 'nullable|boolean',
            'fee_enabled'  => 'nullable|boolean',
            'fee_amount'   => 'nullable|numeric|min:0',
            'title'        => [Rule::requiredIf($item->isCustom()), 'string', 'max:255'],
            'qualify_count'=> 'nullable|integer|min:1',
            'max_per_school'=> 'nullable|integer|min:1',
            'gender'             => 'nullable|in:male,female,mixed,open',
            'participant_type'   => 'nullable|in:individual,group,team',
            'age_group'          => 'nullable|string|max:20',
            'class_group'        => 'nullable|in:lp,up,hs,hss,open',
            'kids_band'          => 'nullable|string|max:20',
            'stage_type'         => 'nullable|in:on_stage,off_stage',
            'venue_type'         => 'nullable|in:indoor,outdoor',
            'sport_discipline'   => 'nullable|string|max:40',
            'competition_format' => 'nullable|string|max:30',
            'head_key'           => $this->catalogHeadKeyRule($meta['eventType']),
        ]);

        foreach (['gender', 'participant_type', 'age_group', 'class_group', 'kids_band', 'stage_type', 'venue_type', 'sport_discipline', 'competition_format', 'head_key'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $item->{$field} = $data[$field] === '' ? null : $data[$field];
            }
        }

        if ($item->isCustom() && isset($data['title'])) {
            $item->title = $data['title'];
        }

        if (array_key_exists('is_enabled', $data)) {
            $item->is_enabled = (bool) $data['is_enabled'];
        }
        if (array_key_exists('fee_enabled', $data)) {
            $item->fee_enabled = (bool) $data['fee_enabled'];
        }
        if (array_key_exists('fee_amount', $data)) {
            $item->fee_amount = $data['fee_amount'];
        }
        if (array_key_exists('qualify_count', $data)) {
            $item->qualify_count = $data['qualify_count'];
        }
        if (array_key_exists('max_per_school', $data)) {
            $item->max_per_school = $data['max_per_school'];
        }

        $item->save();

        $audit->festCatalog($this->sahodaya->id, $program, FestPageActivity::CATALOG_MASTER, 'catalog.item.updated', "Catalog item updated: {$item->title}");

        return back()->with('success', 'Catalog item updated.');
    }

    public function bulk(Request $request, string $tenantId, string $program, PlatformAuditLogger $audit)
    {
        $meta = $this->programMeta($program);
        $data = $request->validate([
            'item_ids'     => 'required|array|min:1',
            'item_ids.*'   => 'integer|exists:fest_catalog_items,id',
            'is_enabled'   => 'nullable|boolean',
            'fee_enabled'  => 'nullable|boolean',
            'fee_amount'   => 'nullable|numeric|min:0',
        ]);

        $query = FestCatalogItem::forProgram($this->sahodaya->id, $meta['eventType'])
            ->whereIn('id', $data['item_ids']);

        $updates = [];
        if (array_key_exists('is_enabled', $data)) {
            $updates['is_enabled'] = (bool) $data['is_enabled'];
        }
        if (array_key_exists('fee_enabled', $data)) {
            $updates['fee_enabled'] = (bool) $data['fee_enabled'];
        }
        if (array_key_exists('fee_amount', $data)) {
            $updates['fee_amount'] = $data['fee_amount'];
            $updates['fee_enabled'] = true;
        }

        abort_if($updates === [], 422, 'No changes selected.');

        $count = $query->update($updates);

        $audit->festCatalog($this->sahodaya->id, $program, FestPageActivity::CATALOG_MASTER, 'catalog.items.bulk', "Bulk updated {$count} catalog item(s)", ['count' => $count]);

        return back()->with('success', "{$count} catalog item(s) updated.");
    }

    public function destroy(string $tenantId, FestCatalogItem $item, PlatformAuditLogger $audit)
    {
        $program = $this->catalogProgramSlug(request());
        $meta = $this->programMeta($program);
        abort_if($item->tenant_id !== $this->sahodaya->id || $item->event_type !== $meta['eventType'], 403);
        abort_unless($item->isCustom(), 422, 'Only custom catalog items can be removed.');

        $title = $item->title;
        $item->delete();

        $audit->festCatalog($this->sahodaya->id, $program, FestPageActivity::CATALOG_MASTER, 'catalog.item.deleted', "Custom item removed: {$title}");

        return back()->with('success', 'Custom item removed from catalog.');
    }

    public function importToEvent(Request $request, string $tenantId, FestEvent $event, FestCatalogService $catalogService, PlatformAuditLogger $audit)
    {
        $program = $this->catalogProgramSlug($request);
        $meta = $this->programMeta($program);
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($event->event_type !== $meta['eventType'], 422, 'Event type does not match this program.');

        $data = $request->validate([
            'class_groups'     => 'nullable|array',
            'class_groups.*'   => 'in:lp,up,hs,hss,open',
            'item_ids'         => 'nullable|array',
            'item_ids.*'       => 'integer|exists:fest_catalog_items,id',
            'catalog_section'  => 'nullable|string|max:40',
        ]);

        $itemIds = $data['item_ids'] ?? null;
        if ($itemIds === null && filled($data['catalog_section'] ?? null) && ($data['catalog_section'] ?? 'all') !== 'all') {
            $sectionMeta = FestCatalogSections::find($meta['eventType'], $data['catalog_section'], $this->sahodaya->id);
            if ($sectionMeta) {
                $q = FestCatalogItem::forProgram($this->sahodaya->id, $meta['eventType'])
                    ->where('is_enabled', true);
                FestCatalogSections::applyFilter($q, $sectionMeta['filter']);
                $itemIds = $q->pluck('id')->all();
            }
        }

        $count = $catalogService->importEnabledToEvent(
            $event,
            $data['class_groups'] ?? null,
            $itemIds,
        );

        $audit->festCatalog($this->sahodaya->id, $program, FestPageActivity::CATALOG_ASSIGN, 'catalog.imported', "Imported {$count} item(s) into {$event->title}", [
            'event_id' => $event->id,
            'count'    => $count,
        ]);

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}")
            ->with('success', "{$count} enabled catalog item(s) imported into \"{$event->title}\".");
    }

    /** @return array{meta: array, program: array, summary: array, sections: list, taxonomy: array, ageGroupLabels: array} */
    private function catalogContext(string $program, FestCatalogService $catalogService): array
    {
        $meta = $this->programMeta($program);
        $catalogService->ensureSeeded($this->sahodaya->id, $meta['eventType']);

        return [
            'meta'           => $meta,
            'program'        => array_merge($meta, [
                'slug'   => $program,
                'prefix' => ProgramRouteMap::prefixFromSlug($program),
            ]),
            'summary'        => $catalogService->summary($this->sahodaya->id, $meta['eventType']),
            'sections'       => FestCatalogSections::summaries($this->sahodaya->id, $meta['eventType']),
            'taxonomy'       => app(FestTaxonomyRegistry::class)->forTenant($this->sahodaya->id)->allLabels(),
            'taxonomyMastersUrl' => "/sahodaya-admin/{$this->sahodaya->id}/taxonomy-masters",
            'ageGroupLabels' => FestSportsAgeGroup::labels(),
        ];
    }

    /** @return list<\App\Models\FestEvent> */
    private function programEvents(string $eventType)
    {
        return FestEvent::forTenant($this->sahodaya->id)
            ->ofType($eventType)
            ->visibleInNav()
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'status']);
    }

    /** @return array{slug: string, label: string, description: string, filter: array}|null */
    private function resolveSection(string $eventType, ?string $section): ?array
    {
        if ($section === null || $section === '') {
            return ['slug' => 'all', 'label' => 'All items', 'description' => 'Complete catalog', 'filter' => []];
        }

        if ($section === 'all') {
            return ['slug' => 'all', 'label' => 'All items', 'description' => 'Complete catalog', 'filter' => []];
        }

        $found = FestCatalogSections::find($eventType, $section, $this->sahodaya->id);
        abort_unless($found !== null, 404);

        return $found;
    }

    private function buildItemQuery(string $eventType, ?array $sectionMeta, Request $request)
    {
        $query = FestCatalogItem::forProgram($this->sahodaya->id, $eventType)
            ->orderBy('display_order')
            ->orderBy('title');

        if ($sectionMeta && ($sectionMeta['filter'] ?? []) !== []) {
            FestCatalogSections::applyFilter($query, $sectionMeta['filter']);
        }

        if ($request->filled('enabled')) {
            $query->where('is_enabled', $request->boolean('enabled'));
        }
        if ($request->filled('age_group')) {
            $query->where('age_group', $request->string('age_group'));
        }
        if ($request->filled('gender')) {
            $query->where('gender', $request->string('gender'));
        }
        if ($request->filled('venue_type')) {
            $query->where('venue_type', $request->string('venue_type'));
        }
        if ($request->filled('sport_discipline')) {
            $query->where('sport_discipline', $request->string('sport_discipline'));
        }
        if ($request->filled('participant_type')) {
            $query->where('participant_type', $request->string('participant_type'));
        }
        if ($request->filled('class_group')) {
            $query->where('class_group', $request->string('class_group'));
        }
        if ($request->filled('head_key')) {
            if ($request->string('head_key') === '__none__') {
                $query->whereNull('head_key');
            } else {
                $query->where('head_key', $request->string('head_key'));
            }
        }
        if ($request->filled('q')) {
            $term = '%'.strtolower(trim($request->string('q'))).'%';
            $query->whereRaw('LOWER(title) LIKE ?', [$term]);
        }

        return $query;
    }

    /** Resolve fest program slug from catalog URL path (never from route defaults — avoids param-order clashes). */
    private function catalogProgramSlug(Request $request): string
    {
        $parts = explode('/', trim($request->path(), '/'));

        if (($parts[2] ?? '') === 'programs') {
            return $parts[3] ?? abort(404);
        }

        $prefix = $parts[2] ?? abort(404);

        return ProgramRouteMap::slugFromPrefix($prefix);
    }

    /** @return array{slug: string, eventType: string, label: string} */
    private function programMeta(string $program): array
    {
        $meta = app(\App\Services\Events\FestCompetitionTypeRegistry::class)
            ->forTenant($this->sahodaya->id)
            ->programMeta($program);
        abort_unless($meta !== null, 404);

        return [
            'slug' => $meta['slug'],
            'eventType' => $meta['eventType'],
            'label' => $meta['label'],
        ];
    }

    /** @return array<string, mixed> */
    private function validateItem(Request $request, string $eventType): array
    {
        $registry = app(FestTaxonomyRegistry::class)->forTenant($this->sahodaya->id);
        $registry->ensureDefaults();

        $rules = [
            'title'              => 'required|string|max:255',
            'participant_type'   => ['nullable', $registry->validationRule('participant_type')],
            'result_method'      => ['nullable', $registry->validationRule('result_method')],
            'gender'             => ['nullable', $registry->validationRule('gender')],
            'class_group'        => 'nullable|in:lp,up,hs,hss,open',
            'age_group'          => 'nullable|in:u8,u10,u11,u12,u14,u17,u19,open',
            'kids_band'          => 'nullable|in:pre_kg,lkg,ukg,class1,class2,open',
            'qualify_count'      => 'nullable|integer|min:1',
            'max_per_school'     => 'nullable|integer|min:1',
            'fee_amount'         => 'nullable|numeric|min:0',
            'stage_type'         => ['nullable', $registry->validationRule('stage_type')],
            'venue_type'         => ['nullable', $registry->validationRule('venue_type')],
            'competition_format' => 'nullable|string|max:30',
            'sport_discipline'   => 'nullable|string|max:40',
            'category'           => 'nullable|string|max:30',
            'min_playing'        => 'nullable|integer|min:1',
            'max_playing'        => 'nullable|integer|min:1',
            'max_subs'           => 'nullable|integer|min:0',
            'max_squad'          => 'nullable|integer|min:1',
            'min_squad'          => 'nullable|integer|min:1',
            'standbys'           => 'nullable|integer|min:0',
            'head_key'           => $this->catalogHeadKeyRule($eventType, required: $eventType === 'sports'),
        ];

        $data = $request->validate($rules);
        $data['participant_type'] = $data['participant_type'] ?? 'individual';
        $data['gender'] = $data['gender'] ?? 'open';
        $data['max_per_school'] = $data['max_per_school'] ?? 1;
        $data['qualify_count'] = $data['qualify_count'] ?? 2;

        if ($eventType === 'sports') {
            $data['category'] = 'sports';
        }

        if (FestTeamSquadRules::isMultiPerson($data['participant_type'])) {
            $merged = FestTeamSquadRules::mergeIntoItem($request->only([
                'min_playing', 'max_playing', 'max_subs', 'max_squad', 'min_squad', 'standbys',
            ]));
            if ($merged['criteria_json']) {
                $data['criteria_json'] = $merged['criteria_json'];
            }
            $data['min_group_size'] = $merged['min_group_size'];
            $data['max_group_size'] = $merged['max_group_size'];
            $fixed = FestTeamSquadRules::defaultSizeFor($data['participant_type']);
            if ($fixed && empty($data['min_group_size']) && empty($data['max_group_size'])) {
                $data['min_group_size'] = $fixed;
                $data['max_group_size'] = $fixed;
            }
        }

        unset($data['min_playing'], $data['max_playing'], $data['max_subs'], $data['max_squad'], $data['min_squad'], $data['standbys']);

        return $data;
    }

    /** @return list<array{key: string, name: string, sport_discipline: ?string}> */
    private function catalogItemHeadOptions(string $eventType): array
    {
        if ($eventType !== 'sports') {
            return [];
        }

        app(FestItemHeadService::class)->ensureCatalogHeads($this->sahodaya->id, 'sports');

        return FestItemHead::forTenant($this->sahodaya->id)
            ->whereNull('event_id')
            ->orderBy('sort_order')
            ->get(['catalog_key', 'name', 'sport_discipline'])
            ->map(fn (FestItemHead $head) => [
                'key' => $head->catalog_key,
                'name' => $head->name,
                'sport_discipline' => $head->sport_discipline,
            ])
            ->all();
    }

    /** @return array<int, mixed> */
    private function catalogHeadKeyRule(string $eventType, bool $required = false): array
    {
        if ($eventType !== 'sports') {
            return ['nullable', 'string', 'max:120'];
        }

        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:120',
            Rule::exists('fest_item_heads', 'catalog_key')->where(
                fn ($query) => $query->where('tenant_id', $this->sahodaya->id)->whereNull('event_id'),
            ),
        ];
    }
}
