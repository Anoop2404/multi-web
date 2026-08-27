<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\CsvSafety;
use App\Http\Controllers\SahodayaAdmin\Concerns\ResolvesRegionAwareReportEvent;
use App\Models\FestAttendance;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestAttendanceImportService;
use App\Services\Events\FestNumberingService;
use App\Support\FestPageActivity;
use Illuminate\Http\Request;

class FestAttendanceController extends SahodayaAdminController
{
    use ResolvesRegionAwareReportEvent;

    /**
     * region_id-aware for the same reason as FestReportController's
     * REGION_ID_AWARE_IDS reports: reached via a region tile that now routes through the
     * parent hub, $event->reportableEventIds() on the raw hub would show every region's
     * attendance combined; reached via the child's own id directly, it would also pull
     * in the hub's own uncopied rows alongside the child's. Only this read-only index()
     * is touched — store()/importStore() (which actually write attendance) are left
     * exactly as they were: intentionally, marking attendance is a live operational flow
     * this change was not run against the test suite for, and a write path is a far
     * worse place to guess wrong than a report view. See this implementation's final
     * status report.
     */
    public function index(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $event = $this->regionAwareTargetEvent($request, $event);

        $event->load([
            'items' => fn ($query) => $query->where('is_enabled', true),
        ]);

        // For sports season events (parent hub), registrations live under
        // child events — filtering by event_id alone returns nothing.
        $eventIds = $event->reportableEventIds();

        $participants = FestParticipant::whereHas('registration', fn ($q) => $q
            ->whereIn('event_id', $eventIds)
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->whereHas('item', fn ($itemQuery) => $itemQuery->where('is_enabled', true)))
            // Exclude unfilled standby slots and any row with no actual person
            // attached (student_id/teacher_id both null) — these aren't real
            // attendees and were showing up as blank rows with no name.
            ->where('participant_role', '!=', 'standby')
            ->where(fn ($q) => $q->whereNotNull('student_id')->orWhereNotNull('teacher_id'))
            ->with(['registration.item', 'registration.school', 'student.schoolClass', 'teacher', 'group'])
            ->get();

        foreach ($participants as $participant) {
            if ($participant->student) {
                $participant->student->setAttribute(
                    'photo_url',
                    $participant->student->sahodayaPhotoUrl($this->sahodaya->id),
                );
            }
        }

        $attendance = FestAttendance::whereIn('event_id', $eventIds)
            ->get()
            ->keyBy(fn ($a) => $a->item_id.'-'.$a->participant_id);

        // So the page can warn before flipping someone to absent after they already
        // have a score — the existing mark isn't retracted when attendance changes,
        // it just silently stays in the results. See Documents/Fest_Improvements_Proposal.md.
        $markedParticipantIds = \App\Models\FestMark::whereIn('event_id', $eventIds)
            ->whereIn('participant_id', $participants->pluck('id'))
            ->where(fn ($q) => $q->whereNotNull('grade')->orWhereNotNull('score')->orWhereNotNull('position'))
            ->pluck('participant_id')
            ->all();

        return $this->inertia('Sahodaya/Events/Attendance', $this->withEventActivity($event, FestPageActivity::ATTENDANCE, [
            'event' => $event,
            'participants' => $participants,
            'attendance' => $attendance,
            'childEvents' => $event->sportEventDropdownOptions(),
            'markedParticipantIds' => $markedParticipantIds,
        ]));
    }

    public function store(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $eventIds = $event->reportableEventIds();

        if ($request->boolean('bulk')) {
            return $this->bulkStore($request, $event, $eventIds, $audit);
        }

        $data = $request->validate([
            'item_id' => 'required|exists:fest_event_items,id',
            'participant_id' => 'required|exists:fest_participants,id',
            'status' => 'required|in:present,absent',
        ]);

        // Cross-scope validation: verify the participant belongs to this event (or
        // one of its child events for a sports season hub) and item.
        $participant = FestParticipant::with('registration')->findOrFail($data['participant_id']);
        abort_if(! in_array($participant->registration->event_id, $eventIds, true), 422, 'Participant does not belong to this event.');
        abort_if($participant->registration->item_id !== (int) $data['item_id'], 422, 'Participant does not belong to this item.');

        $participantIds = $this->expandToTeam($event, $data['item_id'], $data['participant_id']);

        foreach ($participantIds as $participantId) {
            FestAttendance::updateOrCreate(
                ['item_id' => $data['item_id'], 'participant_id' => $participantId],
                [
                    'event_id' => $event->id,
                    'status' => $data['status'],
                    'marked_by' => $request->user()->id,
                    'marked_at' => now(),
                ]
            );
        }

        $personName = $participant->student?->name ?? $participant->teacher?->name ?? $participant->group?->name ?? "Participant #{$data['participant_id']}";
        $chestNo = $participant->group?->chest_no ?? $participant->chest_no;
        $chestLabel = $chestNo ? "Chest #{$chestNo}" : "Participant #{$data['participant_id']}";
        $itemModel = FestEventItem::find($data['item_id']);
        $itemTitle = $itemModel?->title ? " in {$itemModel->title}" : '';
        $statusLabel = ucfirst($data['status']);

        $audit->festEvent($event, FestPageActivity::ATTENDANCE, 'fest.attendance.saved', "Attendance marked {$statusLabel} for {$chestLabel} - {$personName}{$itemTitle}", [
            'participant_id' => $data['participant_id'],
            'chest_no'       => $chestNo,
            'participant'    => $personName,
            'item_id'        => $data['item_id'],
            'item_title'     => $itemModel?->title,
            'status'         => $data['status'],
            'team_size'      => count($participantIds),
        ]);

        return back()->with('success', 'Attendance saved.');
    }

    /**
     * For a team/group item, marking one member also marks every squad
     * member the same way — attendance applies to the whole team, not just
     * whoever happened to be clicked.
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
            ->whereHas('registration', fn ($q) => $q->whereIn('event_id', $event->reportableEventIds())->where('item_id', $itemId))
            ->where(function ($q) {
                $q->whereNull('participant_role')
                    ->orWhere('participant_role', '!=', 'standby');
            })
            ->pluck('id')
            ->all();
    }

    private function bulkStore(Request $request, FestEvent $event, array $eventIds, PlatformAuditLogger $audit)
    {
        $data = $request->validate([
            'item_id' => 'required|exists:fest_event_items,id',
            'participant_ids' => 'required|array|min:1',
            'participant_ids.*' => 'exists:fest_participants,id',
            'status' => 'required|in:present,absent',
        ]);

        // Cross-scope validation for bulk: verify all participants belong to this event (or child events) and item.
        $mismatch = FestParticipant::whereIn('id', $data['participant_ids'])
            ->whereHas('registration', fn ($q) => $q
                ->whereNotIn('event_id', $eventIds)
                ->orWhere('item_id', '!=', (int) $data['item_id']))
            ->exists();
        abort_if($mismatch, 422, 'One or more participants do not belong to this event or item.');

        $expandedIds = collect($data['participant_ids'])
            ->flatMap(fn ($id) => $this->expandToTeam($event, (int) $data['item_id'], (int) $id))
            ->unique()
            ->values()
            ->all();
        $data['participant_ids'] = $expandedIds;

        foreach ($data['participant_ids'] as $participantId) {
            FestAttendance::updateOrCreate(
                ['item_id' => $data['item_id'], 'participant_id' => $participantId],
                [
                    'event_id' => $event->id,
                    'status' => $data['status'],
                    'marked_by' => $request->user()->id,
                    'marked_at' => now(),
                ]
            );
        }

        $itemModel = FestEventItem::find($data['item_id']);
        $itemTitle = $itemModel?->title ? " in {$itemModel->title}" : '';
        $statusLabel = ucfirst($data['status']);
        $count = count($data['participant_ids']);

        $audit->festEvent($event, FestPageActivity::ATTENDANCE, 'fest.attendance.bulk_saved', "Bulk attendance marked {$statusLabel} for {$count} participant(s){$itemTitle}", [
            'count'      => $count,
            'item_id'    => $data['item_id'],
            'item_title' => $itemModel?->title,
            'status'     => $data['status'],
        ]);

        return back()->with('success', count($data['participant_ids']).' attendance record(s) saved.');
    }

    public function importTemplate(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            CsvSafety::fputcsv($out, ['reg_no', 'item_title', 'participant_id', 'status']);
            CsvSafety::fputcsv($out, ['S2024001', 'Mono Act', '', 'present']);
            fclose($out);
        }, "fest-attendance-{$event->id}-template.csv", ['Content-Type' => 'text/csv']);
    }

    public function importStore(Request $request, string $tenantId, FestEvent $event, FestAttendanceImportService $importService, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);

        $result = $importService->importFromCsv(
            $event,
            $request->file('file')->getRealPath(),
            $request->user()->id,
        );

        $audit->festEvent($event, FestPageActivity::ATTENDANCE, 'fest.attendance.imported', "Imported {$result['imported']} attendance record(s)", [
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
        ]);

        $message = "Imported {$result['imported']} attendance record(s).";
        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} skipped.";
        }

        return back()
            ->with($result['imported'] > 0 ? 'success' : 'error', $message)
            ->with('importErrors', array_slice($result['errors'], 0, 20));
    }
}
