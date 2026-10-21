<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\SchoolAdmin\Concerns\ForwardsFestProgramActions;
use App\Models\FestAthleticRecord;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestRecordBreak;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestAthleticRecordService;
use App\Services\Events\FestEventNotifier;
use App\Services\Events\FestQualificationService;
use App\Services\Events\FestSchoolEventFeeService;
use App\Services\Events\FestSportsAutoRankService;
use App\Support\FestClassGroupScheme;
use App\Support\FestSportsMeasurementHelper;
use Illuminate\Http\Request;

class SportsMeetController extends SchoolAdminController
{
    use ForwardsFestProgramActions;

    protected function festProgramPrefix(): string
    {
        return 'sports';
    }

    // The meet page hosts every school-level round, not just sports: sports rounds
    // run the full in-platform meet, non-sports rounds are results-only
    // (FestEvent::isSchoolResultsOnly()) and use this same hub to enter/submit
    // their own winners — the submit flow is what makes that work.
    private function assertSchoolMeetEvent(FestEvent $event): void
    {
        abort_unless($event->level_round === 'school', 404);
        abort_unless($event->conducting_school_id === $this->school->id, 403, 'Only the school conducting this round can manage it.');
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
    }

    private function assertSahodayaSportsEvent(FestEvent $event): void
    {
        abort_unless($event->event_type === 'sports', 404);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        abort_if($event->level_round === 'school', 404);
    }

    public function myEvent(string $tenantId, FestEvent $event, string $tab = 'overview')
    {
        $this->assertSchoolMeetEvent($event);

        $event->load(['items', 'parentEvent:id,title,status']);

        $registrations = FestRegistration::where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->with(['item', 'participants.student.schoolHouse', 'participants.student.schoolClass', 'participants.teacher'])
            ->get();

        $marks = FestMark::where('event_id', $event->id)->get()->keyBy('participant_id');

        $resultsByItem = FestMark::where('event_id', $event->id)
            ->whereNotNull('position')
            ->with(['participant.student.schoolHouse', 'participant.student.schoolClass', 'participant.teacher', 'item'])
            ->orderBy('item_id')
            ->orderBy('position')
            ->get()
            ->groupBy('item_id')
            ->map(fn ($group) => $group->map(fn (FestMark $m) => [
                'position'         => $m->position,
                'name'             => $m->participant?->student?->name ?? $m->participant?->teacher?->name,
                'house'            => $m->participant?->student?->schoolHouse?->only('id', 'name', 'color'),
                'class'            => $m->participant?->student?->schoolClass?->name,
                'measurement'      => trim("{$m->measurement_value} {$m->measurement_unit}"),
                'score'            => $m->score,
            ])->values());

        // 'state' events are locked, read-only qualifier placeholders — never a real round
        // to link a school's event under. Excluded 2026-07-31 (see FestProgramController fix).
        // A results-only round (non-sports) links to its own program-type parent; sports
        // meets keep the sports-only list.
        $parentEvents = FestEvent::where('tenant_id', $this->school->parent_id)
            ->when($event->isSchoolResultsOnly(),
                fn ($q) => $q->where('event_type', $event->event_type),
                fn ($q) => $q->where('event_type', 'sports'))
            ->where('level_round', 'sahodaya')
            ->whereIn('status', ['published', 'registration_open', 'ongoing', 'completed'])
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'level_round', 'status']);

        $houseStandings = app(\App\Services\Events\SchoolHouseFestPointsService::class)->rankingForSchool($this->school->id, $event->id);

        $athleticRecords = FestAthleticRecord::where('event_id', $event->id)
            ->with('item')
            ->orderBy('item_id')
            ->get();

        $recordBreaks = FestRecordBreak::where('event_id', $event->id)
            ->with(['item', 'participant.student.schoolHouse', 'participant.student.schoolClass'])
            ->orderByDesc('broken_at')
            ->get();

        return $this->inertia('School/Sports/MyEvent', [
            'tab'             => $tab,
            'event'           => $event,
            'parentEvents'    => $parentEvents,
            'registrations'   => $registrations,
            'marks'           => $marks,
            'resultsByItem'   => $resultsByItem,
            'houseStandings'  => $houseStandings,
            'athleticRecords' => $athleticRecords,
            'recordBreaks'    => $recordBreaks,
            'classGroups'     => FestClassGroupScheme::labels(null, $event->rootEvent()),
            'ageGroupLabels'  => config('fest_item_taxonomy.age_group', []),
            'stats'           => [
                'items'          => $event->items->count(),
                'registrations'  => $registrations->count(),
                'marks_entered'  => $marks->count(),
                'ranked'         => FestMark::where('event_id', $event->id)->whereNotNull('position')->count(),
                'records'        => $athleticRecords->count(),
            ],
        ]);
    }

    public function sahodayaEvent(Request $request, string $tenantId, FestEvent $event)
    {
        $this->assertSahodayaSportsEvent($event);

        return redirect("/school-admin/{$tenantId}/sports/events/{$event->id}/registration");
    }

    public function linkParent(Request $request, string $tenantId, FestEvent $event)
    {
        return app(FestProgramController::class)->linkParent($request, $tenantId, $event);
    }

    public function storeMark(Request $request, string $tenantId, FestEvent $event, \App\Services\Events\FestMarkSaveService $markSave, PlatformAuditLogger $audit)
    {
        $this->assertSchoolMeetEvent($event);

        // All school rounds (sports meets and results-only rounds alike) record marks
        // through the school marks flow — results-only rounds simply use it to enter
        // their winners' ranks instead of the sports measurement/auto-rank pair.
        return app(FestProgramController::class)->storeMark($request, $tenantId, $event, $markSave, $audit);
    }

    public function bulkStoreMarks(Request $request, string $tenantId, FestEvent $event, \App\Services\Events\FestMarkSaveService $markSave, PlatformAuditLogger $audit)
    {
        $this->assertSchoolMeetEvent($event);

        return app(FestProgramController::class)->bulkStoreMarks($request, $tenantId, $event, $markSave, $audit);
    }

    public function autoRankItem(Request $request, string $tenantId, FestEvent $event, FestEventItem $item, FestSportsAutoRankService $ranker)
    {
        $this->assertSchoolMeetEvent($event);
        abort_if($item->event_id !== $event->id, 404);

        // Auto-rank ranks from recorded measurements/judged scores — a results-only
        // round has neither, so the school's own recorded ranks stand as-is.
        if ($event->isSchoolResultsOnly()) {
            return back()->with('info', 'This round is results-only — your recorded ranks are the winners; nothing to auto-rank.');
        }

        $basis = $request->input('rank_basis');
        \App\Services\Events\EventLifecycleGate::allowMarkEntryForItem($event, $item);
        $result = $ranker->rankItem($event, $item, $basis);

        return back()->with('success', "Auto-ranked {$result['ranked']} athlete(s) for {$result['item_title']}.");
    }

    public function storeRecord(Request $request, string $tenantId, FestEvent $event, FestAthleticRecordService $service, PlatformAuditLogger $audit)
    {
        $this->assertSchoolMeetEvent($event);

        $data = $request->validate([
            'item_id'          => 'required|exists:fest_event_items,id',
            'class_group'      => 'required|in:lp,up,hs,hss,open',
            'gender'           => 'required|in:male,female,open',
            'record_direction' => 'required|in:lower_better,higher_better',
            'record_value'     => 'required|string|max:50',
            'record_unit'      => 'nullable|string|max:20',
            'holder_name'      => 'nullable|string|max:255',
            'notes'            => 'nullable|string|max:500',
            'record_date'      => 'nullable|date',
        ]);

        $item = FestEventItem::where('event_id', $event->id)->findOrFail($data['item_id']);
        $method = $data['record_direction'] === 'lower_better' ? 'time' : 'distance';
        $parsedValue = FestSportsMeasurementHelper::parseMeasurement($data['record_value'], $method, $data['record_unit']);

        if ($parsedValue === null || $parsedValue <= 0) {
            return back()->withErrors(['record_value' => 'Enter a valid positive measurement value (e.g. 11.45 or 5.45).']);
        }

        FestAthleticRecord::updateOrCreate(
            [
                'event_id'    => $event->id,
                'item_id'     => $item->id,
                'class_group' => $data['class_group'],
                'gender'      => $data['gender'],
            ],
            [
                'record_direction' => $data['record_direction'],
                'record_value'     => $parsedValue,
                'record_unit'      => $data['record_unit'] ?: ($data['record_direction'] === 'lower_better' ? 's' : 'm'),
                'holder_name'      => $data['holder_name'] ?? null,
                'notes'            => $data['notes'] ?? null,
                'record_date'      => $data['record_date'] ?? now()->toDateString(),
            ]
        );

        $audit->festEvent($event, 'records', 'fest.athletic_record.saved', 'School meet athletic record saved', [
            'item_id' => $data['item_id'],
        ]);

        return back()->with('success', 'Standing record saved successfully.');
    }

    public function destroyRecord(string $tenantId, FestEvent $event, FestAthleticRecord $record, PlatformAuditLogger $audit)
    {
        $this->assertSchoolMeetEvent($event);
        abort_if($record->event_id !== $event->id, 404);

        $record->delete();

        $audit->festEvent($event, 'records', 'fest.athletic_record.deleted', 'School meet athletic record removed');

        return back()->with('success', 'Record removed.');
    }

    public function toggleRecordTracking(string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        $this->assertSchoolMeetEvent($event);

        $event->update([
            'record_tracking_enabled' => ! $event->record_tracking_enabled,
        ]);

        $audit->festEvent($event, 'settings', 'fest.event.record_tracking_toggled', 'Toggled athletic record tracking', [
            'record_tracking_enabled' => $event->record_tracking_enabled,
        ]);

        return back()->with('success', 'Record tracking ' . ($event->record_tracking_enabled ? 'enabled' : 'disabled') . '.');
    }

    public function submitWinners(string $tenantId, FestQualificationService $qualification, FestEventNotifier $notifier)
    {
        $candidates = $qualification->schoolSportsWinnerCandidates($this->school);

        // Rounds with NO winners listed here are due: their top-ranked participants were
        // either already submitted (visible in the submitted list below) or nothing has
        // been recorded/published yet. Nudge the school about the second case so a round
        // doesn't sit silently skipped by bulk promotion (FestQualificationService).
        $due = [];
        foreach ($this->dueSchoolRounds() as $round) {
            $due[] = [
                'id'       => $round->id,
                'title'    => $round->title,
                'event_type' => $round->event_type,
                'results_published' => (bool) $round->results_published,
            ];
        }

        return $this->inertia('School/Sports/SubmitWinners', [
            'school' => $this->school->only('id', 'name'),
            'events' => $candidates,
            'due'    => $due,
        ]);
    }

    /** School-level rounds this school conducts that still have nothing submitted. */
    private function dueSchoolRounds(): \Illuminate\Support\Collection
    {
        $qualifications = \App\Models\FestQualification::whereHas('event', fn ($q) => $q
                ->where('conducting_school_id', $this->school->id)
                ->where('level_round', 'school'))
            ->get()
            ->map(fn ($q) => $q->event_id);

        return FestEvent::query()
            ->where('conducting_school_id', $this->school->id)
            ->where('level_round', 'school')
            ->whereNotNull('parent_event_id')
            ->with('parentEvent:id,title')
            ->orderByDesc('event_start')
            ->get()
            ->filter(function (FestEvent $round) use ($qualifications) {
                if ($qualifications->contains($round->id)) {
                    return false; // already submitted at least one winner
                }
                return $round->isSchoolResultsOnly()
                    || ! $round->results_published;
            });
    }

    public function storeWinners(Request $request, string $tenantId, FestQualificationService $qualification, FestEventNotifier $notifier, PlatformAuditLogger $audit)
    {
        $data = $request->validate([
            'school_event_id' => 'required|exists:fest_events,id',
            'mark_ids'        => 'required|array|min:1',
            'mark_ids.*'      => 'integer|exists:fest_marks,id',
        ]);

        $schoolEvent = FestEvent::findOrFail($data['school_event_id']);
        // This is the school's own submit flow — it only manages rounds it conducts
        // itself, and submitting another school's winners through it is not a thing.
        abort_if($schoolEvent->conducting_school_id !== $this->school->id, 403, 'Only the school conducting this round can submit its winners.');
        $result = $qualification->submitSchoolSportsWinners($this->school, $schoolEvent, $data['mark_ids']);

        if ($result['submitted'] > 0 && $schoolEvent->parent_event_id) {
            $target = FestEvent::find($schoolEvent->parent_event_id);
            if ($target) {
                if ($target->event_type === 'sports') {
                    $notifier->sportsWinnersReceived($target, $this->school, $result['submitted']);
                }
                $audit->festEvent($target, 'sports', 'sports.winners.submitted',
                    "{$this->school->name} submitted {$result['submitted']} winner(s) for {$schoolEvent->title}", [
                    'school_id' => $this->school->id,
                    'count'     => $result['submitted'],
                ]);
            }
        }

        $message = "{$result['submitted']} winner(s) submitted.";
        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} skipped (already submitted or invalid).";
        }

        return back()->with('success', $message);
    }
}
