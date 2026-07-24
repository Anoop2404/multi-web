<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsItemHeadReportContext;
use App\Models\FestAttendance;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestMarkSheetUpload;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\FestMarkCriteriaService;
use App\Services\Events\FestMarkSaveService;
use App\Services\Events\FestNumberingService;
use App\Services\Events\FestRankPointService;
use App\Services\Events\FestSportsAutoRankService;
use App\Support\FestPageActivity;
use App\Support\TenantBranding;
use App\Support\TenantStorage;
use Illuminate\Http\Request;

class FestMarkEntryController extends SahodayaAdminController
{
    use BuildsItemHeadReportContext;

    public function index(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $event->load('items');

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

        $itemIds = $itemId ? [$itemId] : [];

        $registrations = FestRegistration::where('event_id', $event->id)
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->where(function ($query) {
                $query->whereHas('participants', fn ($qp) => $qp->whereNotNull('chest_no'))
                    ->orWhereHas('groups', fn ($qg) => $qg->whereNotNull('chest_no'));
            })
            ->when($itemIds !== [], fn ($q) => $q->whereIn('item_id', $itemIds))
            ->when($itemIds === [], fn ($q) => $q->whereRaw('1 = 0'))
            ->with(['item', 'school', 'participants.student', 'participants.teacher', 'participants.group'])
            ->get();

        $marks = FestMark::where('event_id', $event->id)->get()->keyBy('participant_id');

        $attendance = FestAttendance::where('event_id', $event->id)
            ->get()
            ->mapWithKeys(fn (FestAttendance $row) => [
                "{$row->item_id}-{$row->participant_id}" => ['status' => $row->status],
            ])
            ->all();

        $selectedHeadId = match (true) {
            $headId === 0 => 'other',
            $headId !== null => $headId,
            default => null,
        };

        $childEvents = [];
        if ($event->event_type === 'sports') {
            $seasonId = $event->parent_event_id ?? $event->id;
            $childEvents = FestEvent::where('parent_event_id', $seasonId)
                ->orWhere('id', $seasonId)
                ->ofType('sports')
                ->orderBy('title')
                ->get(['id', 'title', 'parent_event_id'])
                ->all();
        }

        $criteria = [];
        $judgeCount = 1;
        $judgeScores = [];
        $sheetUploads = [];
        $selectedItemModel = $itemId ? FestEventItem::find($itemId) : null;
        if ($selectedItemModel) {
            $criteriaService = app(FestMarkCriteriaService::class);
            $criteria = $criteriaService->criteriaForItem($selectedItemModel)->values()->all();
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
        }

        return $this->inertia('Sahodaya/Events/MarkEntry', $this->withEventActivity($event, FestPageActivity::MARKS, [
            'event'          => $event,
            'registrations'  => $registrations,
            'marks'          => $marks,
            'attendance'     => $attendance,
            'selectedHeadId' => $selectedHeadId,
            'selectedItemId' => $itemId,
            'competitionUrl' => "/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}/competition",
            'rankPoints'     => $event->event_type === 'sports'
                ? app(FestRankPointService::class)->listForEvent($event)
                : [],
            'childEvents'      => $childEvents,
            'criteria'         => $criteria,
            'judgeCount'       => $judgeCount,
            'judgeScores'      => $judgeScores,
            'sheetUploads'     => $sheetUploads,
            'cumulativeSheetUrl' => ($itemId && $judgeCount > 1)
                ? "/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}/reports/mark-criteria-sheet?item_id={$itemId}"
                : null,
        ]));
    }

    public function store(Request $request, string $tenantId, FestEvent $event, FestMarkSaveService $markSave, FestMarkCriteriaService $criteriaService, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        EventLifecycleGate::allowMarkEntry($event);

        $data = $request->validate([
            'participant_id'    => 'required|exists:fest_participants,id',
            'item_id'           => 'required|exists:fest_event_items,id',
            'grade'             => 'nullable|in:A,A+,B,C',
            'position'          => 'nullable|integer|min:1|max:255',
            'score'             => 'nullable|numeric|min:0',
            'measurement_value' => 'nullable|string|max:50',
            'measurement_unit'  => 'nullable|string|max:20',
            'judge_scores'      => 'nullable|array',
            'judge_scores.*'    => 'nullable|numeric|min:0',
        ]);

        $item = FestEventItem::find($data['item_id']);
        $judgeScores = $data['judge_scores'] ?? null;
        unset($data['judge_scores']);

        $teamParticipantIds = $this->expandToTeam($event, (int) $data['item_id'], (int) $data['participant_id']);

        $result = null;
        foreach ($teamParticipantIds as $participantId) {
            $rowData = $data;

            if ($item && $judgeScores !== null && $criteriaService->hasJudgePanel($item)) {
                $rowData['score'] = $criteriaService->saveParticipantJudgeScores($item, $participantId, $judgeScores);
                $rowData['grade'] = null;
            }

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
            'criteria'              => 'nullable|array',
            'criteria.*.id'         => 'nullable|integer',
            'criteria.*.label'      => 'required|string|max:100',
            'criteria.*.max_score'  => 'nullable|numeric|min:0.5',
        ]);

        $criteria = $criteriaService->saveCriteria($event, $item, $data['criteria'] ?? []);
        $criteriaService->setJudgeCount($item, $data['judge_count'] ?? 1);

        $audit->festEvent($event, FestPageActivity::MARKS, 'fest.mark.criteria.saved', "Mark criteria updated for item #{$item->id}", [
            'item_id' => $item->id,
            'criteria_count' => $criteria->count(),
            'judge_count' => $data['judge_count'] ?? 1,
        ]);

        return back()->with('success', 'Marking criteria saved.');
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
        $scores = $judgeCount > 1 ? $criteriaService->judgeScoresForItem($item) : [];

        $isGroup = $numbering->isGroupItem($item);

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
                $name = $p->group?->team_name ?: 'Team';
                $chest = $p->group?->chest_no;
            } else {
                $name = $p->student?->name ?? $p->teacher?->name ?? '—';
                $chest = $numbering->effectiveChestNumber($p);
            }

            if ($chest === null) {
                continue;
            }

            $regNo = $p->student?->reg_no ?? $p->teacher?->reg_no ?? null;
            $rowScores = $scores[$p->id] ?? [];

            $judgeValues = [];
            for ($j = 1; $j <= $judgeCount; $j++) {
                $judgeValues[] = $rowScores[$j] ?? null;
            }

            $rows[] = [
                'chest_no' => $chest,
                'reg_no'   => $regNo,
                'name'     => $name,
                'school'   => strtoupper($p->registration?->school?->name ?? ''),
                'scores'   => $judgeValues,
                'total'    => array_sum(array_map(fn ($v) => (float) ($v ?? 0), $rowScores)),
            ];
        }

        usort($rows, fn ($a, $b) => ($a['chest_no'] ?? PHP_INT_MAX) <=> ($b['chest_no'] ?? PHP_INT_MAX));

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('fest.reports.mark-criteria-sheet', [
            'event'      => $event,
            'item'       => $item,
            'judgeCount' => $judgeCount,
            'rows'       => $rows,
            'orgName'    => $this->sahodaya->name ?? 'Sahodaya',
            'logoSrc'    => TenantBranding::logoEmbedSrc($this->sahodaya),
        ])->download("mark-sum-sheet-{$item->id}.pdf");
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

        $sheets = [];

        foreach ($items as $item) {
            $isGroup = $numbering->isGroupItem($item);
            $criteria = $criteriaService->criteriaForItem($item);
            $judgeCount = $criteriaService->judgeCountForItem($item);

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

                if ($chest === null) {
                    continue;
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
                        'item'         => $item,
                        'criteria'     => $criteria,
                        'rows'         => $rows,
                        'sheet_label'  => "JUDGE {$judgeNumber} SHEET",
                        'is_sum_sheet' => false,
                        'judge_count'  => $judgeCount,
                    ];
                }

                $sheets[] = [
                    'item'         => $item,
                    'criteria'     => $criteria,
                    'rows'         => $rows,
                    'sheet_label'  => 'SUM SHEET',
                    'is_sum_sheet' => true,
                    'judge_count'  => $judgeCount,
                ];
            } else {
                $sheets[] = [
                    'item'         => $item,
                    'criteria'     => $criteria,
                    'rows'         => $rows,
                    'sheet_label'  => null,
                    'is_sum_sheet' => false,
                    'judge_count'  => 1,
                ];
            }
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('fest.reports.mark-entry-sheet', [
            'sahodaya' => $this->sahodaya,
            'event'    => $event,
            'sheets'   => $sheets,
            'logoSrc'  => TenantBranding::logoEmbedSrc($this->sahodaya),
        ])->setPaper('a4', 'landscape');

        $fileName = $itemId
            ? "mark-entry-sheet-item-{$itemId}.pdf"
            : "mark-entry-sheets-{$event->id}.pdf";

        return $pdf->download($fileName);
    }

    public function autoRankItem(string $tenantId, FestEvent $event, FestEventItem $item, FestSportsAutoRankService $ranker)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 404);
        abort_unless($event->event_type === 'sports', 422, 'Auto-rank applies to sports events only.');

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
