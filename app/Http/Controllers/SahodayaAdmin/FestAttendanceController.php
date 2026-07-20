<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestPageActivity;
use App\Models\FestAttendance;
use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Http\Request;
use App\Services\Events\FestAttendanceImportService;

class FestAttendanceController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $event->load('items');

        $participants = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('status', 'approved'))
            // Exclude unfilled standby slots and any row with no actual person
            // attached (student_id/teacher_id both null) — these aren't real
            // attendees and were showing up as blank rows with no name.
            ->where('participant_role', '!=', 'standby')
            ->where(fn ($q) => $q->whereNotNull('student_id')->orWhereNotNull('teacher_id'))
            ->with(['registration.item', 'registration.school', 'student', 'teacher'])
            ->get();

        $attendance = FestAttendance::where('event_id', $event->id)
            ->get()
            ->keyBy(fn ($a) => $a->item_id.'-'.$a->participant_id);

        return $this->inertia('Sahodaya/Events/Attendance', $this->withEventActivity($event, FestPageActivity::ATTENDANCE, [
            'event'        => $event,
            'participants' => $participants,
            'attendance'   => $attendance,
        ]));
    }

    public function store(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        if ($request->boolean('bulk')) {
            return $this->bulkStore($request, $event, $audit);
        }

        $data = $request->validate([
            'item_id'        => 'required|exists:fest_event_items,id',
            'participant_id' => 'required|exists:fest_participants,id',
            'status'         => 'required|in:present,absent',
        ]);

        FestAttendance::updateOrCreate(
            ['item_id' => $data['item_id'], 'participant_id' => $data['participant_id']],
            [
                'event_id'  => $event->id,
                'status'    => $data['status'],
                'marked_by' => $request->user()->id,
                'marked_at' => now(),
            ]
        );

        $audit->festEvent($event, FestPageActivity::ATTENDANCE, 'fest.attendance.saved', 'Attendance saved', [
            'participant_id' => $data['participant_id'],
            'item_id'        => $data['item_id'],
            'status'         => $data['status'],
        ]);

        return back()->with('success', 'Attendance saved.');
    }

    private function bulkStore(Request $request, FestEvent $event, PlatformAuditLogger $audit)
    {
        $data = $request->validate([
            'item_id'         => 'required|exists:fest_event_items,id',
            'participant_ids' => 'required|array|min:1',
            'participant_ids.*' => 'exists:fest_participants,id',
            'status'          => 'required|in:present,absent',
        ]);

        foreach ($data['participant_ids'] as $participantId) {
            FestAttendance::updateOrCreate(
                ['item_id' => $data['item_id'], 'participant_id' => $participantId],
                [
                    'event_id'  => $event->id,
                    'status'    => $data['status'],
                    'marked_by' => $request->user()->id,
                    'marked_at' => now(),
                ]
            );
        }

        $audit->festEvent($event, FestPageActivity::ATTENDANCE, 'fest.attendance.bulk_saved', count($data['participant_ids']).' attendance record(s) saved', [
            'count'   => count($data['participant_ids']),
            'item_id' => $data['item_id'],
            'status'  => $data['status'],
        ]);

        return back()->with('success', count($data['participant_ids']).' attendance record(s) saved.');
    }

    public function importTemplate(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['reg_no', 'item_title', 'participant_id', 'status']);
            fputcsv($out, ['S2024001', 'Mono Act', '', 'present']);
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
            'skipped'  => $result['skipped'],
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
