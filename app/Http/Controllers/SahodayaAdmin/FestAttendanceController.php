<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestAttendance;
use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use Illuminate\Http\Request;

class FestAttendanceController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $event->load('items');

        $participants = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('status', 'approved'))
            ->with(['registration.item', 'student', 'teacher'])
            ->get();

        $attendance = FestAttendance::where('event_id', $event->id)
            ->get()
            ->keyBy(fn ($a) => $a->item_id.'-'.$a->participant_id);

        return $this->inertia('Sahodaya/Events/Attendance', [
            'event'        => $event,
            'participants' => $participants,
            'attendance'   => $attendance,
        ]);
    }

    public function store(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        if ($request->boolean('bulk')) {
            return $this->bulkStore($request, $event);
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

        return back()->with('success', 'Attendance saved.');
    }

    private function bulkStore(Request $request, FestEvent $event)
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

        return back()->with('success', count($data['participant_ids']).' attendance record(s) saved.');
    }
}
