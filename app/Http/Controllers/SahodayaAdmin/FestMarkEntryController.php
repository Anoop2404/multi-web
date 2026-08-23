<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsItemHeadReportContext;
use App\Models\FestAttendance;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestMarkCriterion;
use App\Models\FestMarkSheetUpload;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestScoringRubricTemplate;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\FestHeadItemNavigationService;
use App\Services\Events\FestMarkCriteriaService;
use App\Services\Events\FestMarkSaveService;
use App\Services\Events\FestNumberingService;
use App\Services\Events\FestRankPointService;
use App\Services\Events\FestSportsAutoRankService;
use App\Support\FestPageActivity;
use App\Support\TenantBranding;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FestMarkEntryController extends SahodayaAdminController
{
    use BuildsItemHeadReportContext;
    use \App\Http\Controllers\SahodayaAdmin\Concerns\ResolvesRegionAwareReportEvent;

    public function index(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $event = $this->regionAwareTargetEvent($request, $event);
        $event->load('items');

        // Head/item tabs for the picker — reuses the same navigation data ChestNumbers,
        // ItemHeadOps, and the Reports hub already build item pickers from, instead of
        // shipping the flat item list to a hand-rolled widget on this page.
        $nav = app(FestHeadItemNavigationService::class)->navigationForEvent($event);

        $headId = $this->resolveHeadQueryParam($request->query('head_id') ?? $request->query('head'));
        $itemId = $request->integer('item_id') ?: null;

        // Mark entry, like attendance and chest numbers, is always scoped to a
        // single item — there is no "all items combined" view. If the request
        // didn't pin one down, default to the first eligible item (honoring
        // a head filter if given) instead of dumping every item on one page.
        if (! $itemId) {
            $fallbackQuery = ($event->event_type === 'sports' && $headId !== null && $headId > 0)
                ? FestEventItem::where('event_id', $headId)
                : FestEventItem::where('event_id', $event->id);

            $fallbackQuery->where('is_enabled', true);

            if ($event->event_type !== 'sports' || $headId === null || $headId <= 0) {
                if ($headId === 0) {
                    $fallbackQuery->whereNull('head_id');
                } elseif ($headId !== null) {
                    $fallbackQuery->where('head_id', $headId);
                }
            }

            $itemId = $fallbackQuery->orderBy('id')->value('id');
        }

        $itemIds = $itemId ? $event->reportableItemIds([$itemId]) : [];

        $eventIds = $event->reportableEventIds();

        $registrations = FestRegistration::whereIn('event_id', $eventIds)
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->when($itemIds !== [], fn ($q) => $q->whereIn('item_id', $itemIds))
            ->when($itemIds === [], fn ($q) => $q->whereRaw('1 = 0'))
            ->with(['item', 'school', 'participants.student', 'participants.teacher', 'participants.group'])
            ->get();

        $marks = FestMark::whereIn('event_id', $eventIds)->get()->keyBy('participant_id');

        $attendance = FestAttendance::whereIn('event_id', $eventIds)
            ->get()
            ->mapWithKeys(fn (FestAttendance $row) => [
                "{$row->item_id}-{$row->participant_id}" => ['status' => $row->status],
            ])
            ->all();

        $selectedHeadId = match (true) {
            $headId === 0 => 'other',
            $headId !== null => $headId,
            // No head_id given and this event has no real FestItemHead rows at all — the
            // whole catalog sits in one synthetic "Other items" group (see
            // FestHeadItemNavigationService::navigationForEvent()). Default straight into
            // it so the item search/dropdown is visible immediately, matching the old
            // pill list's behavior of showing every item with no extra click. Events that
            // do have real heads keep requiring a head to be picked first, unchanged.
            ! $nav['hasItemHeads'] => 'other',
            default => null,
        };

        $childEvents = $event->sportEventDropdownOptions();

        // Which items already have at least one scoring column configured, so the
        // item picker can show progress across 140+ items without opening each one.
        $configuredItemIds = FestMarkCriterion::whereIn('item_id', $event->items->pluck('id'))
            ->distinct()
            ->pluck('item_id')
            ->all();

        $gradeOptions = app(\App\Services\Events\FestGradePointService::class)->validGradesForEvent($event);

        $judgeCount = 1;
        $judgeScores = [];
        $sheetUploads = [];
        $missingChestCount = 0;
        $selectedItemModel = $itemId ? FestEventItem::find($itemId) : null;
        if ($selectedItemModel) {
            $criteriaService = app(FestMarkCriteriaService::class);
            $judgeCount = $criteriaService->judgeCountForItem($selectedItemModel);
            if ($judgeCount > 1) {
                $judgeScores = $criteriaService->judgeScoresForItem($selectedItemModel);
            }

            $sheetUploads = FestMarkSheetUpload::where('item_id', $itemId)
                ->with('uploadedBy:id,name')
                ->latest()
                ->get()
                ->map(fn (FestMarkSheetUpload $u) => [
                    'id'            => $u->id,
                    'original_name' => $u->original_name,
                    'uploaded_by'   => $u->uploadedBy?->name,
                    'uploaded_at'   => $u->created_at?->format('d M Y, h:i A'),
                    'downloadUrl'   => "/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}/mark-sheet-uploads/{$u->id}",
                ])
                ->all();

            // So the page can warn before anyone prints/judges off a sheet with blank
            // chest numbers — cheaper to flag here than to let a judge discover it on paper.
            // Mirrors markEntrySheet()'s own group-vs-individual resolution so the count
            // matches what that PDF will actually show.
            $numbering = app(FestNumberingService::class);
            $isGroupItem = $numbering->isGroupItem($selectedItemModel);
            $seenGroups = [];
            $missingChestCount = FestParticipant::whereHas('registration', fn ($q) => $q
                    ->where('event_id', $event->id)
                    ->where('item_id', $itemId)
                    ->whereNotIn('status', ['rejected', 'withdrawn']))
                ->where('participant_role', '!=', 'standby')
                ->with('group')
                ->get()
                ->filter(function (FestParticipant $p) use ($isGroupItem, $numbering, &$seenGroups) {
                    if ($isGroupItem && $p->group_id) {
                        if (isset($seenGroups[$p->group_id])) {
                            return false;
                        }
                        $seenGroups[$p->group_id] = true;

                        return $p->group?->chest_no === null;
                    }

                    return $numbering->effectiveChestNumber($p) === null;
                })
                ->count();
        }

        return $this->inertia('Sahodaya/Events/MarkEntry', $this->withEventActivity($event, FestPageActivity::MARKS, [
            'event'          => $event,
            'registrations'  => $registrations,
            'marks'          => $marks,
            'attendance'     => $attendance,
            'selectedHeadId' => $selectedHeadId,
            'selectedItemId' => $itemId,
            'headItemGroups' => $nav['headItemGroups'],
            'configuredItemIds' => $configuredItemIds,
            'gradeOptions' => $gradeOptions,
            'gradeRules'   => \App\Models\FestGradeConfig::where('event_id', $event->id)
                ->where(function ($q) use ($itemId) {
                    $q->where('item_id', $itemId)->orWhereNull('item_id');
                })
                ->get(['grade', 'item_id', 'min_score', 'max_score', 'min_percent', 'max_percent']),
            'rankPointsByType' => app(FestRankPointService::class)->rowsForAllTypes($event),
            'childEvents'      => $childEvents,
            'judgeCount'       => $judgeCount,
            'judgeScores'      => $judgeScores,
            'selectedItemTotalMarks' => $selectedItemModel?->total_marks,
            'sheetUploads'     => $sheetUploads,
            'missingChestCount' => $missingChestCount,
            'cumulativeSheetUrl' => $itemId
                ? "/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}/reports/mark-criteria-sheet?item_id={$itemId}"
                : null,
        ]));
    }

    public function store(Request $request, string $tenantId, FestEvent $event, FestMarkSaveService $markSave, FestMarkCriteriaService $criteriaService, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        // Resolved before validate() so a judge's own subtotal can be capped at the item's
        // Total Marks — the one scoring path that previously had no ceiling at all (unlike
        // per-criterion scores, which are already clamped to each criterion's own max_score).
        // Total Marks is the item's overall ceiling across every judge (e.g. 200), not each
        // judge's own scale, so each judge is capped at that split evenly across the panel —
        // otherwise every judge could independently reach the full total and their sum would
        // blow past it.
        $item = $request->filled('item_id') ? FestEventItem::find($request->input('item_id')) : null;
        $perJudgeMax = $item?->total_marks !== null
            ? $item->total_marks / $criteriaService->judgeCountForItem($item)
            : null;

        $data = $request->validate([
            'participant_id'    => 'required|exists:fest_participants,id',
            'item_id'           => 'required|exists:fest_event_items,id',
            'grade'             => ['nullable', app(\App\Services\Events\FestGradePointService::class)->gradeValidationRule($event)],
            'position'          => 'nullable|integer|min:1|max:255',
            'score'             => 'nullable|numeric|min:0',
            'measurement_value' => 'nullable|string|max:50',
            'measurement_unit'  => 'nullable|string|max:20',
            'judge_scores'      => 'nullable|array',
            'judge_scores.*'    => array_filter([
                'nullable',
                'numeric',
                'min:0',
                $perJudgeMax !== null ? 'max:'.$perJudgeMax : null,
            ]),
        ]);

        // Now phase-aware — no-op while phase_mode_enabled is off (see EventLifecycleGate). Reordered validate() before the gate so a malformed item_id 422s on validation, not the business-rule check.
        EventLifecycleGate::allowMarkEntryForItem($event, $item);

        $judgeScores = $data['judge_scores'] ?? null;
        unset($data['judge_scores']);

        // The judge panel always sends every judge's current box, even when the admin
        // only meant to change something else on the row (rank, attendance) — if those
        // boxes happen to be empty (e.g. this participant's judge breakdown was never
        // entered through this screen), that's NOT "the judges scored zero," it's "there's
        // nothing here to save." Only recompute/overwrite the combined score when at
        // least one judge box actually has a value, so an empty panel can never silently
        // wipe an already-saved score.
        $hasRealJudgeScore = is_array($judgeScores)
            && collect($judgeScores)->contains(fn ($v) => $v !== null && $v !== '');

        $teamParticipantIds = $this->expandToTeam($event, (int) $data['item_id'], (int) $data['participant_id']);

        $result = null;
        foreach ($teamParticipantIds as $participantId) {
            $rowData = $data;

            if ($item && $hasRealJudgeScore && $criteriaService->hasJudgePanel($item)) {
                $rowData['score'] = $criteriaService->saveParticipantJudgeScores($item, $participantId, $judgeScores);
            }

            // Grade-from-score auto-derivation happens once, inside save() itself
            // (which also knows how to respect an explicit grade change/clear) —
            // duplicating it here used to pre-fill grade before save() could tell
            // that was a revert-to-null, silently discarding it.
            $result = $markSave->save($event, [...$rowData, 'participant_id' => $participantId], $request->user()->id);
        }

        $audit->festEvent($event, FestPageActivity::MARKS, 'fest.mark.saved', "Mark saved for participant #{$data['participant_id']}", [
            'participant_id' => $data['participant_id'],
            'item_id'        => $data['item_id'],
            'team_size'      => count($teamParticipantIds),
        ]);

        return back()->with('success', $result['message'] ?? 'Mark saved.');
    }

    public function saveCriteria(Request $request, string $tenantId, FestEvent $event, FestEventItem $item, FestMarkCriteriaService $criteriaService, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 404);

        $data = $request->validate([
            'judge_count'           => 'nullable|integer|min:1|max:20',
            'total_marks'           => 'nullable|numeric|min:0',
            'criteria'              => 'nullable|array',
            'criteria.*.id'         => 'nullable|integer',
            'criteria.*.label'      => 'required|string|max:100',
            'criteria.*.max_score'  => 'nullable|numeric|min:0.5',
        ]);

        $criteria = $criteriaService->saveCriteria($event, $item, $data['criteria'] ?? []);
        $criteriaService->setJudgeCount($item, $data['judge_count'] ?? 1);
        $item->update(['total_marks' => $data['total_marks'] ?? null]);

        $audit->festEvent($event, FestPageActivity::MARK_SETTINGS, 'fest.mark.criteria.saved', "Mark criteria updated for item #{$item->id}", [
            'item_id' => $item->id,
            'criteria_count' => $criteria->count(),
            'judge_count' => $data['judge_count'] ?? 1,
        ]);

        return back()->with('success', 'Marking criteria saved.');
    }

    public function copyCriteria(Request $request, string $tenantId, FestEvent $event, FestEventItem $item, FestMarkCriteriaService $criteriaService, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 404);

        $data = $request->validate([
            'source_item_id' => 'required|integer|exists:fest_event_items,id',
        ]);

        $sourceItem = FestEventItem::findOrFail($data['source_item_id']);
        abort_if($sourceItem->event_id !== $event->id, 404);

        $criteria = $criteriaService->copyCriteriaFromItem($event, $sourceItem, $item);

        $audit->festEvent($event, FestPageActivity::MARK_SETTINGS, 'fest.mark.criteria.copied', "Mark criteria copied from item #{$sourceItem->id} to item #{$item->id}", [
            'source_item_id' => $sourceItem->id,
            'item_id'        => $item->id,
            'criteria_count' => $criteria->count(),
        ]);

        return back()->with('success', "Marking criteria copied from \"{$sourceItem->title}\".");
    }

    public function applyTemplate(Request $request, string $tenantId, FestEvent $event, FestEventItem $item, FestMarkCriteriaService $criteriaService, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 404);

        $data = $request->validate([
            'template_id' => 'required|integer|exists:fest_scoring_rubric_templates,id',
        ]);

        $template = FestScoringRubricTemplate::findOrFail($data['template_id']);
        abort_if($template->tenant_id !== $this->sahodaya->id, 404);

        $criteria = $criteriaService->applyTemplateToItem($event, $template, $item);

        $audit->festEvent($event, FestPageActivity::MARK_SETTINGS, 'fest.mark.criteria.template_applied', "Rubric template \"{$template->name}\" applied to item #{$item->id}", [
            'template_id' => $template->id,
            'item_id'     => $item->id,
            'criteria_count' => $criteria->count(),
        ]);

        return back()->with('success', "Rubric template \"{$template->name}\" applied.");
    }

    /** Per-item marking config: judge count, scoring criteria columns, total marks. */
    public function markSettings(Request $request, string $tenantId, FestEvent $event, FestMarkCriteriaService $criteriaService)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $event = $this->regionAwareTargetEvent($request, $event);
        $event->load('items');

        $nav = app(FestHeadItemNavigationService::class)->navigationForEvent($event);

        $headId = $this->resolveHeadQueryParam($request->query('head_id') ?? $request->query('head'));
        $itemId = $request->integer('item_id') ?: null;

        if (! $itemId) {
            $fallbackQuery = ($event->event_type === 'sports' && $headId !== null && $headId > 0)
                ? FestEventItem::where('event_id', $headId)
                : FestEventItem::where('event_id', $event->id);

            $fallbackQuery->where('is_enabled', true);

            if ($event->event_type !== 'sports' || $headId === null || $headId <= 0) {
                if ($headId === 0) {
                    $fallbackQuery->whereNull('head_id');
                } elseif ($headId !== null) {
                    $fallbackQuery->where('head_id', $headId);
                }
            }

            $itemId = $fallbackQuery->orderBy('id')->value('id');
        }

        $selectedHeadId = match (true) {
            $headId === 0 => 'other',
            $headId !== null => $headId,
            ! $nav['hasItemHeads'] => 'other',
            default => null,
        };

        $configuredItemIds = FestMarkCriterion::whereIn('item_id', $event->items->pluck('id'))
            ->distinct()
            ->pluck('item_id')
            ->all();

        $rubricTemplates = FestScoringRubricTemplate::forTenant($this->sahodaya->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $criteria = [];
        $judgeCount = 1;
        $selectedItem = null;
        $selectedItemModel = $itemId ? FestEventItem::find($itemId) : null;
        if ($selectedItemModel) {
            $criteria = $criteriaService->criteriaForItem($selectedItemModel)->values()->all();
            $judgeCount = $criteriaService->judgeCountForItem($selectedItemModel);
            $selectedItem = $selectedItemModel->only('id', 'title', 'item_code', 'total_marks');
        }

        return $this->inertia('Sahodaya/Events/MarkSettings', $this->withEventActivity($event, FestPageActivity::MARK_SETTINGS, [
            'event'          => $event,
            'headItemGroups' => $nav['headItemGroups'],
            'hasItemHeads'   => $nav['hasItemHeads'],
            'selectedHeadId' => $selectedHeadId,
            'selectedItemId' => $itemId,
            'selectedItem'   => $selectedItem,
            'configuredItemIds' => $configuredItemIds,
            'rubricTemplates' => $rubricTemplates,
            'criteria'       => $criteria,
            'judgeCount'     => $judgeCount,
            'childEvents'    => $event->sportEventDropdownOptions(),
        ]));
    }

    /** Bulk Total Marks / Judge Count editor — scoped to this event only (region-aware, matches markSettings()). */
    public function markSettingsBulk(Request $request, string $tenantId, FestEvent $event, FestMarkCriteriaService $criteriaService)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $event = $this->regionAwareTargetEvent($request, $event);
        $event->load('items');

        $items = $event->items
            ->sortBy('title')
            ->map(fn (FestEventItem $item) => [
                'id'          => $item->id,
                'title'       => $item->title,
                'item_code'   => $item->item_code,
                'total_marks' => $item->total_marks,
                'judge_count' => $criteriaService->judgeCountForItem($item),
            ])
            ->values();

        return $this->inertia('Sahodaya/Events/MarkSettingsBulk', $this->withEventActivity($event, FestPageActivity::MARK_SETTINGS, [
            'event'       => $event,
            'items'       => $items,
            'childEvents' => $event->sportEventDropdownOptions(),
        ]));
    }

    public function bulkUpdateMarkSettings(Request $request, string $tenantId, FestEvent $event, FestMarkCriteriaService $criteriaService, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'items'                => 'required|array',
            'items.*.id'           => 'required|integer|exists:fest_event_items,id',
            'items.*.total_marks'  => 'nullable|numeric|min:0',
            'items.*.judge_count'  => 'nullable|integer|min:1|max:20',
        ]);

        $updatedCount = 0;

        DB::transaction(function () use ($data, $event, $criteriaService, &$updatedCount) {
            foreach ($data['items'] as $itemData) {
                $item = FestEventItem::where('event_id', $event->id)->find($itemData['id']);
                if (! $item) {
                    continue;
                }

                $item->update(['total_marks' => $itemData['total_marks'] ?? null]);
                $criteriaService->setJudgeCount($item, $itemData['judge_count'] ?? 1);
                $updatedCount++;
            }
        });

        $audit->festEvent($event, FestPageActivity::MARK_SETTINGS, 'fest.mark.settings.bulk_updated', "Total Marks / Judge Count bulk-updated for {$updatedCount} item(s)", [
            'updated_count' => $updatedCount,
        ]);

        return back()->with('success', "Total Marks / Judge Count saved for {$updatedCount} item(s).");
    }

    /**
     * For a team/group item, the mark applies to the whole squad — saving
     * it writes the same grade/position/score to every member's row so
     * per-participant certificate/results/points logic keeps working
     * unchanged, while the entry screen shows and edits it once per team.
     *
     * @return list<int>
     */
    private function expandToTeam(FestEvent $event, int $itemId, int $participantId): array
    {
        $participant = FestParticipant::with('registration.item')->find($participantId);
        $item = $participant?->registration?->item;

        if (! $participant || ! $item || ! $participant->group_id
            || ! app(FestNumberingService::class)->isGroupItem($item)) {
            return [$participantId];
        }

        return FestParticipant::where('group_id', $participant->group_id)
            ->whereHas('registration', fn ($q) => $q->where('event_id', $event->id)->where('item_id', $itemId))
            ->where(function ($q) {
                $q->whereNull('participant_role')
                    ->orWhere('participant_role', '!=', 'standby');
            })
            ->pluck('id')
            ->all();
    }

    /**
     * Digitally-filled Sum Sheet for a judge-panel item — Sl No / chest /
     * reg id / one column per judge (their subtotal, as already typed into
     * Mark Entry) / grand total, one row per participant (or per team for
     * group items). Mirrors the printed blank Sum Sheet, but pre-filled.
     */
    public function cumulativeSheet(Request $request, string $tenantId, FestEvent $event, FestMarkCriteriaService $criteriaService, FestNumberingService $numbering)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $itemId = $request->integer('item_id');
        abort_unless($itemId, 422, 'Select an item.');

        $item = FestEventItem::findOrFail($itemId);
        abort_if($item->event_id !== $event->id, 404);

        $judgeCount = $criteriaService->judgeCountForItem($item);

        // Multi-judge items keep per-judge subtotals in FestMarkJudgeScore. A single-judge
        // item never writes there — its one score lives directly on FestMark — so build the
        // same participant_id => [judge_number => score] shape from that instead, letting
        // the rest of this method (and the shared blade view) treat both cases identically.
        if ($judgeCount > 1) {
            $scores = $criteriaService->judgeScoresForItem($item);
        } else {
            $scores = FestMark::where('item_id', $item->id)
                ->pluck('score', 'participant_id')
                ->map(fn ($score) => [1 => $score === null ? null : (float) $score])
                ->all();
        }

        $isGroup = $numbering->isGroupItem($item);

        // Chest number only — no name/school. Judges and convenors work off chest numbers
        // everywhere else in this app (blind judging); this tabulation sheet shouldn't be
        // the one place that leaks who's who.
        $participants = FestParticipant::whereHas('registration', fn ($q) => $q
                ->whereIn('event_id', $event->reportableEventIds())
                ->where('item_id', $item->id)
                ->whereNotIn('status', ['rejected', 'withdrawn']))
            ->where('participant_role', '!=', 'standby')
            ->with(['group'])
            ->get();

        $rows = [];
        $seenGroups = [];

        foreach ($participants as $p) {
            if ($isGroup && $p->group_id) {
                if (isset($seenGroups[$p->group_id])) {
                    continue;
                }
                $seenGroups[$p->group_id] = true;
                $chest = $p->group?->chest_no;
            } else {
                $chest = $numbering->effectiveChestNumber($p);
            }

            $rowScores = $scores[$p->id] ?? [];

            $judgeValues = [];
            for ($j = 1; $j <= $judgeCount; $j++) {
                $judgeValues[] = $rowScores[$j] ?? null;
            }

            // Blank (not 0) when nothing's been entered yet — this sheet doubles as a paper
            // form for typing in judges' subtotals by hand, and a "0" reads as a real score.
            $hasAnyScore = collect($judgeValues)->contains(fn ($v) => $v !== null);

            $rows[] = [
                'chest_no' => $chest,
                'scores'   => $judgeValues,
                'total'    => $hasAnyScore ? array_sum(array_map(fn ($v) => (float) ($v ?? 0), $rowScores)) : null,
            ];
        }

        usort($rows, fn ($a, $b) => ((int) preg_replace('/[^0-9]/', '', (string) ($a['chest_no'] ?? 999999))) <=> ((int) preg_replace('/[^0-9]/', '', (string) ($b['chest_no'] ?? 999999))));

        $sheetTitle = 'Digital Sum Sheet';
        $categoryLabel = $this->itemCategoryLabel($item, \App\Support\FestClassGroupScheme::labels(null, $event));

        $nameParts = [$event->title];
        if ($categoryLabel) {
            $nameParts[] = $categoryLabel;
        }
        $nameParts[] = $item->title;
        $nameParts[] = $sheetTitle;
        $fileName = \Illuminate\Support\Str::slug(implode(' ', $nameParts)).'.pdf';

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('fest.reports.mark-criteria-sheet', [
            'event'         => $event,
            'item'          => $item,
            'judgeCount'    => $judgeCount,
            'sheetTitle'    => $sheetTitle,
            'categoryLabel' => $categoryLabel,
            'rows'          => $rows,
            'orgName'       => $this->sahodaya->name ?? 'Sahodaya',
            'logoSrc'       => TenantBranding::logoEmbedSrc($this->sahodaya),
        ])->setPaper('a4', 'portrait')->download($fileName);
    }

    /**
     * Printable blank scoring sheet for judges: Sl No, Chest No, one blank
     * column per configured marking criterion (or a single "Marks / Score"
     * column when the item has none), and a Total column — nothing else.
     * Landscape, since the criteria columns can run wide.
     */
    public function markEntrySheet(Request $request, string $tenantId, FestEvent $event, FestNumberingService $numbering, FestMarkCriteriaService $criteriaService)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $itemId = $request->integer('item_id');

        $query = FestEventItem::where('event_id', $event->id)->where('is_enabled', true);
        if ($itemId) {
            $query->where('id', $itemId);
        }
        $items = $query->orderBy('display_order')->orderBy('title')->get();

        abort_if($items->isEmpty(), 404, 'No competition items found.');

        // "Category" on a Kalotsav item almost always means its class/age bracket
        // (e.g. "Category 1 — Classes 3 & 4"), not the arts_category genre tag —
        // this is the same scheme FestIdCardService resolves for ID cards.
        $classGroupLabels = \App\Support\FestClassGroupScheme::labels(null, $event);

        $sheets = [];

        foreach ($items as $item) {
            $isGroup = $numbering->isGroupItem($item);
            $criteria = $criteriaService->criteriaForItem($item);
            $judgeCount = $criteriaService->judgeCountForItem($item);
            $categoryLabel = $this->itemCategoryLabel($item, $classGroupLabels);

            $participants = FestParticipant::whereHas('registration', fn ($q) => $q
                    ->where('event_id', $event->id)
                    ->where('item_id', $item->id)
                    ->whereNotIn('status', ['rejected', 'withdrawn']))
                ->where('participant_role', '!=', 'standby')
                ->with(['student', 'teacher', 'registration.school', 'group'])
                ->get();

            $rows = [];
            $seenGroups = [];

            foreach ($participants as $p) {
                if ($isGroup && $p->group_id) {
                    if (isset($seenGroups[$p->group_id])) {
                        continue;
                    }
                    $seenGroups[$p->group_id] = true;
                    $chest = $p->group?->chest_no;
                } else {
                    $chest = $numbering->effectiveChestNumber($p);
                }

                $rows[] = ['chest_no' => $chest];
            }

            usort($rows, fn ($a, $b) => (int) ($a['chest_no'] ?? 999999) <=> (int) ($b['chest_no'] ?? 999999));

            if ($judgeCount > 1) {
                // One identical blank sheet per judge, then a consolidated
                // Sum Sheet (one column per judge + a blank Grand Total) used
                // to combine the judges' paper subtotals before typing the
                // per-judge totals into the online Mark Entry page.
                for ($judgeNumber = 1; $judgeNumber <= $judgeCount; $judgeNumber++) {
                    $sheets[] = [
                        'item'          => $item,
                        'criteria'      => $criteria,
                        'rows'          => $rows,
                        'sheet_label'   => "JUDGE {$judgeNumber} SHEET",
                        'is_sum_sheet'  => false,
                        'judge_count'   => $judgeCount,
                        'category_label' => $categoryLabel,
                    ];
                }

                $sheets[] = [
                    'item'          => $item,
                    'criteria'      => $criteria,
                    'rows'          => $rows,
                    'sheet_label'   => 'SUM SHEET',
                    'is_sum_sheet'  => true,
                    'judge_count'   => $judgeCount,
                    'category_label' => $categoryLabel,
                ];
            } else {
                $sheets[] = [
                    'item'          => $item,
                    'criteria'      => $criteria,
                    'rows'          => $rows,
                    'sheet_label'   => null,
                    'is_sum_sheet'  => false,
                    'judge_count'   => 1,
                    'category_label' => $categoryLabel,
                ];
            }
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('fest.reports.mark-entry-sheet', [
            'sahodaya' => $this->sahodaya,
            'event'    => $event,
            'sheets'   => $sheets,
            'logoSrc'  => TenantBranding::logoEmbedSrc($this->sahodaya),
        ])->setPaper('a4', 'portrait');

        $nameParts = [$event->title];
        if ($itemId) {
            $singleItem = $items->first();
            $singleItemCategory = $this->itemCategoryLabel($singleItem, $classGroupLabels);
            if ($singleItemCategory) {
                $nameParts[] = $singleItemCategory;
            }
            $nameParts[] = $singleItem->title;
        }
        $nameParts[] = 'mark entry sheet';
        $fileName = \Illuminate\Support\Str::slug(implode(' ', $nameParts)).'.pdf';

        return $pdf->download($fileName);
    }

    /**
     * "Category" for a Kalotsav item means its class/age bracket (e.g. "Category 1 —
     * Classes 3 & 4"), not the internal arts_category genre tag. Falls back to the arts
     * genre only when the item has no meaningful class/age scoping (class_group is the
     * universal 'open' bucket), and to null when neither says anything distinctive.
     */
    private function itemCategoryLabel(FestEventItem $item, array $classGroupLabels): ?string
    {
        if ($item->class_group && $item->class_group !== 'open') {
            return $classGroupLabels[$item->class_group] ?? strtoupper($item->class_group);
        }

        if ($item->category && $item->category !== 'general') {
            return ucwords(str_replace(['_', '-'], ' ', $item->category));
        }

        return null;
    }

    public function autoRankItem(string $tenantId, FestEvent $event, FestEventItem $item, FestSportsAutoRankService $ranker)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 404);

        $result = $ranker->rankItem($event, $item);

        return back()->with('success', "Auto-ranked {$result['ranked']} athlete(s) for {$result['item_title']}.");
    }

    /**
     * Attach a scanned photo/PDF of the physically-signed judge mark sheet
     * to an item, as an audit-trail record. Purely a stored document — no
     * data is extracted or written to FestMark/FestMarkCriterionScore.
     */
    public function uploadSheet(Request $request, string $tenantId, FestEvent $event, FestEventItem $item, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 404);

        $data = $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $path = TenantStorage::storeUploadedFile($data['file'], "fest-mark-sheets/{$event->id}");

        $upload = FestMarkSheetUpload::create([
            'event_id'            => $event->id,
            'item_id'             => $item->id,
            'file_path'           => $path,
            'original_name'       => $data['file']->getClientOriginalName(),
            'uploaded_by_user_id' => $request->user()->id,
        ]);

        $audit->festEvent($event, FestPageActivity::MARKS, 'fest.mark_sheet.uploaded', "Signed mark sheet uploaded for {$item->title}", [
            'item_id'   => $item->id,
            'upload_id' => $upload->id,
        ]);

        return back()->with('success', 'Signed mark sheet uploaded.');
    }

    public function downloadSheetUpload(string $tenantId, FestEvent $event, FestMarkSheetUpload $upload)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($upload->event_id !== $event->id, 404);

        $disk = config('filesystems.upload_disk', 'shared');
        if (in_array($disk, ['s3', 'private'], true)) {
            return redirect(\Illuminate\Support\Facades\Storage::disk($disk)->temporaryUrl($upload->file_path, now()->addMinutes(15)));
        }

        return TenantStorage::downloadResponse($this->sahodaya, $upload->file_path);
    }

    public function destroySheetUpload(string $tenantId, FestEvent $event, FestMarkSheetUpload $upload, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($upload->event_id !== $event->id, 404);

        $itemId = $upload->item_id;
        $upload->delete();

        $audit->festEvent($event, FestPageActivity::MARKS, 'fest.mark_sheet.deleted', 'Signed mark sheet upload removed', [
            'item_id' => $itemId,
        ]);

        return back()->with('success', 'Upload removed.');
    }
}
