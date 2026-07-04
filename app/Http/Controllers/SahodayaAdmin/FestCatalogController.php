<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestCatalogItem;
use App\Models\FestEvent;
use App\Services\Events\FestCatalogService;
use App\Services\Events\FestItemCatalogService;
use App\Services\Events\FestTaxonomyRegistry;
use App\Support\FestPageActivity;
use App\Support\FestCatalogSections;
use App\Services\Audit\PlatformAuditLogger;
use App\Support\FestSportsAgeGroup;
use App\Support\FestTeamSquadRules;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FestCatalogController extends SahodayaAdminController
{
    /** @var array<string, array{slug: string, eventType: string, label: string}> */
    private const PROGRAMS = [
        'kalotsav'     => ['slug' => 'kalotsav', 'eventType' => 'kalolsavam', 'label' => 'Kalotsav'],
        'sports-meet'  => ['slug' => 'sports-meet', 'eventType' => 'sports', 'label' => 'Sports Meet'],
        'kids-fest'    => ['slug' => 'kids-fest', 'eventType' => 'kids_fest', 'label' => 'Kids Fest'],
        'teacher-fest' => ['slug' => 'teacher-fest', 'eventType' => 'teacher_fest', 'label' => 'Teacher Fest'],
        'english-fest' => ['slug' => 'english-fest', 'eventType' => 'english_fest', 'label' => 'English Fest'],
        'science-fest' => ['slug' => 'science-fest', 'eventType' => 'science_fest', 'label' => 'Science Fest'],
        'custom'       => ['slug' => 'custom', 'eventType' => 'custom', 'label' => 'Custom Events'],
    ];

    public function index(Request $request, string $tenantId, string $program, FestCatalogService $catalogService)
    {
        $ctx = $this->catalogContext($program, $catalogService);

        return $this->inertia('Sahodaya/Catalog/Hub', $ctx + [
            'events'       => $this->programEvents($ctx['meta']['eventType']),
            'activityLogs' => $this->catalogActivityLogs(FestPageActivity::CATALOG_HUB),
        ]);
    }

    public function master(Request $request, string $tenantId, string $program, FestCatalogService $catalogService, ?string $section = null)
    {
        $ctx = $this->catalogContext($program, $catalogService);
        $sectionMeta = $this->resolveSection($ctx['meta']['eventType'], $section);
        $items = $this->buildItemQuery($ctx['meta']['eventType'], $sectionMeta, $request)->get();

        return $this->inertia('Sahodaya/Catalog/Master', $ctx + [
            'section'      => $sectionMeta,
            'items'        => $items,
            'filters'      => $request->only(['enabled', 'age_group', 'gender', 'venue_type', 'sport_discipline', 'participant_type', 'class_group', 'q']),
            'groupedItems' => app(FestItemCatalogService::class)->groupForDisplay($items, $ctx['meta']['eventType']),
            'activityLogs' => $this->catalogActivityLogs(FestPageActivity::CATALOG_MASTER),
        ]);
    }

    public function list(Request $request, string $tenantId, string $program, FestCatalogService $catalogService, ?string $section = null)
    {
        $ctx = $this->catalogContext($program, $catalogService);
        $sectionMeta = $this->resolveSection($ctx['meta']['eventType'], $section);
        $items = $this->buildItemQuery($ctx['meta']['eventType'], $sectionMeta, $request)->get();

        return $this->inertia('Sahodaya/Catalog/List', $ctx + [
            'section'      => $sectionMeta,
            'items'        => $items,
            'filters'      => $request->only(['enabled', 'q']),
            'groupedItems' => app(FestItemCatalogService::class)->groupForDisplay($items, $ctx['meta']['eventType']),
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
    public function section(Request $request, string $tenantId, string $program, string $section)
    {
        return redirect("/sahodaya-admin/{$tenantId}/programs/{$program}/catalog/master/{$section}");
    }

    public function seed(string $tenantId, string $program, FestCatalogService $catalogService, PlatformAuditLogger $audit)
    {
        $meta = $this->programMeta($program);
        $added = $catalogService->ensureSeeded($this->sahodaya->id, $meta['eventType']);

        $audit->festCatalog($this->sahodaya->id, $program, FestPageActivity::CATALOG_MASTER, 'catalog.seed', $added > 0 ? "Synced {$added} CKSC item(s)" : 'Catalog sync — no new items');

        return back()->with('success', $added > 0 ? "{$added} CKSC item(s) added to master catalog." : 'Master catalog is already up to date.');
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

    public function update(Request $request, string $tenantId, string $program, FestCatalogItem $item, FestCatalogService $catalogService, PlatformAuditLogger $audit)
    {
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
        ]);

        foreach (['gender', 'participant_type', 'age_group', 'class_group', 'kids_band', 'stage_type', 'venue_type', 'sport_discipline', 'competition_format'] as $field) {
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

    public function destroy(string $tenantId, string $program, FestCatalogItem $item, PlatformAuditLogger $audit)
    {
        $meta = $this->programMeta($program);
        abort_if($item->tenant_id !== $this->sahodaya->id || $item->event_type !== $meta['eventType'], 403);
        abort_unless($item->isCustom(), 422, 'Only custom catalog items can be removed.');

        $title = $item->title;
        $item->delete();

        $audit->festCatalog($this->sahodaya->id, $program, FestPageActivity::CATALOG_MASTER, 'catalog.item.deleted', "Custom item removed: {$title}");

        return back()->with('success', 'Custom item removed from catalog.');
    }

    public function importToEvent(Request $request, string $tenantId, string $program, FestEvent $event, FestCatalogService $catalogService, PlatformAuditLogger $audit)
    {
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
            $sectionMeta = FestCatalogSections::find($meta['eventType'], $data['catalog_section']);
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
            'program'        => array_merge($meta, ['slug' => $program]),
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

        $found = FestCatalogSections::find($eventType, $section);
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
        if ($request->filled('q')) {
            $term = '%'.strtolower(trim($request->string('q'))).'%';
            $query->whereRaw('LOWER(title) LIKE ?', [$term]);
        }

        return $query;
    }

    /** @return array{slug: string, eventType: string, label: string} */
    private function programMeta(string $program): array
    {
        abort_unless(isset(self::PROGRAMS[$program]), 404);

        return self::PROGRAMS[$program];
    }

    /** @return array<string, mixed> */
    private function validateItem(Request $request, string $eventType): array
    {
        $rules = [
            'title'              => 'required|string|max:255',
            'participant_type'   => 'nullable|in:individual,group,team',
            'gender'             => 'nullable|in:male,female,mixed,open',
            'class_group'        => 'nullable|in:lp,up,hs,hss,open',
            'age_group'          => 'nullable|in:u8,u10,u11,u12,u14,u17,u19,open',
            'kids_band'          => 'nullable|in:pre_kg,lkg,ukg,class1,class2,open',
            'qualify_count'      => 'nullable|integer|min:1',
            'max_per_school'     => 'nullable|integer|min:1',
            'fee_amount'         => 'nullable|numeric|min:0',
            'stage_type'         => 'nullable|in:on_stage,off_stage',
            'venue_type'         => 'nullable|in:indoor,outdoor',
            'competition_format' => 'nullable|string|max:30',
            'sport_discipline'   => 'nullable|string|max:40',
            'category'           => 'nullable|string|max:30',
            'min_playing'        => 'nullable|integer|min:1',
            'max_playing'        => 'nullable|integer|min:1',
            'max_subs'           => 'nullable|integer|min:0',
            'max_squad'          => 'nullable|integer|min:1',
            'min_squad'          => 'nullable|integer|min:1',
            'standbys'           => 'nullable|integer|min:0',
        ];

        $data = $request->validate($rules);
        $data['participant_type'] = $data['participant_type'] ?? 'individual';
        $data['gender'] = $data['gender'] ?? 'open';
        $data['max_per_school'] = $data['max_per_school'] ?? 1;
        $data['qualify_count'] = $data['qualify_count'] ?? 2;

        if ($eventType === 'sports') {
            $data['category'] = 'sports';
        }

        if (in_array($data['participant_type'], ['team', 'group'], true)) {
            $merged = FestTeamSquadRules::mergeIntoItem($request->only([
                'min_playing', 'max_playing', 'max_subs', 'max_squad', 'min_squad', 'standbys',
            ]));
            if ($merged['criteria_json']) {
                $data['criteria_json'] = $merged['criteria_json'];
            }
            $data['min_group_size'] = $merged['min_group_size'];
            $data['max_group_size'] = $merged['max_group_size'];
        }

        unset($data['min_playing'], $data['max_playing'], $data['max_subs'], $data['max_squad'], $data['min_squad'], $data['standbys']);

        return $data;
    }
}
