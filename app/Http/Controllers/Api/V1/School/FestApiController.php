<?php

namespace App\Http\Controllers\Api\V1\School;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestGroup;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\FestParticipationLimitService;
use App\Services\Events\FestRegistrationEligibilityService;
use App\Services\Events\FestRegistrationImportService;
use App\Services\Events\FestRegistrationService;
use App\Services\Events\FestSchoolEventFeeService;
use Illuminate\Http\Request;

class FestApiController extends SchoolApiController
{
    public function index()
    {
        $events = FestEvent::where('tenant_id', $this->school->parent_id)
            ->listedForSchool($this->school->id)
            ->with('items')
            ->orderByDesc('event_start')
            ->get();

        $registrations = FestRegistration::where('school_id', $this->school->id)
            ->whereIn('event_id', $events->pluck('id'))
            ->with(['event', 'item', 'participants.student', 'participants.teacher'])
            ->get();

        return response()->json(['data' => ['events' => $events, 'registrations' => $registrations]]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'event_id'      => 'required|exists:fest_events,id',
            'item_id'       => 'required|exists:fest_event_items,id',
            'team_name'     => 'nullable|string|max:255',
            'student_ids'   => 'nullable|array',
            'student_ids.*' => 'exists:students,id',
            'teacher_ids'   => 'nullable|array',
            'teacher_ids.*' => 'exists:teachers,id',
            'standby_ids'   => 'nullable|array',
            'standby_ids.*' => 'exists:students,id',
        ]);

        $event = FestEvent::where('tenant_id', $this->school->parent_id)
            ->visibleToSchool($this->school->id)
            ->findOrFail($data['event_id']);

        abort_if($this->school->fest_registration_closed, 422, 'Fest registration is closed for this school.');

        $item = FestEventItem::where('event_id', $event->id)->findOrFail($data['item_id']);

        if ($event->event_type === 'teacher_fest') {
            $registration = app(\App\Services\Events\FestRegistrationCreateService::class)
                ->createForSchool($event, $item, $this->school, $data['teacher_ids'] ?? []);

            return response()->json(['data' => $registration->load(['event', 'item', 'participants'])], 201);
        }

        $registration = app(\App\Services\Events\FestRegistrationCreateService::class)
            ->createForSchool(
                $event,
                $item,
                $this->school,
                $data['student_ids'] ?? [],
                $data['standby_ids'] ?? [],
                $data['team_name'] ?? null,
            );

        return response()->json(['data' => $registration->load(['event', 'item', 'participants'])], 201);
    }

    public function withdraw(Request $request, FestRegistration $registration)
    {
        $event = FestEvent::findOrFail($registration->event_id);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        abort_if($registration->school_id !== $this->school->id, 403);

        abort_unless(
            app(FestRegistrationService::class)->canSchoolCancel($registration, $event),
            422,
            'This registration can no longer be cancelled.'
        );

        app(FestRegistrationService::class)->cancel($registration, $event);

        return response()->json(['data' => ['cancelled' => true]]);
    }

    public function import(Request $request, FestRegistrationImportService $importService)
    {
        $data = $request->validate([
            'event_id' => 'required|exists:fest_events,id',
            'file'     => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $event = FestEvent::findOrFail($data['event_id']);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        EventLifecycleGate::allowRegistration($event);

        $result = $importService->importFromCsv(
            $event,
            $this->school,
            $request->file('file')->getRealPath(),
            $event->event_type === 'teacher_fest',
        );

        return response()->json(['data' => $result], $result['imported'] > 0 ? 201 : 422);
    }

    public function importTemplate(string $program = 'kalotsav')
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['item_id', 'item_title', 'reg_no', 'team_name', 'role']);
            fputcsv($out, ['123', 'Mono Act', 'S2024001', '', 'performer']);
            fclose($out);
        }, 'fest-registration-template.csv', ['Content-Type' => 'text/csv']);
    }

    /** @param array<string, mixed> $data */
    private function storeTeacherRegistration(FestEvent $event, FestEventItem $item, array $data)
    {
        $teacherIds = array_values(array_unique($data['teacher_ids'] ?? []));
        if (count($teacherIds) > 1 && ! in_array($item->participant_type, ['group', 'team'], true)) {
            abort(422, 'This item allows only one teacher.');
        }

        $registration = FestRegistration::create([
            'event_id'     => $data['event_id'],
            'item_id'      => $data['item_id'],
            'school_id'    => $this->school->id,
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        foreach ($teacherIds as $teacherId) {
            abort_if(Teacher::where('id', $teacherId)->where('tenant_id', $this->school->id)->doesntExist(), 403);
            FestParticipant::create([
                'registration_id'  => $registration->id,
                'teacher_id'       => $teacherId,
                'participant_type' => 'teacher',
                'participant_role' => 'performer',
            ]);
        }

        app(FestSchoolEventFeeService::class)->recalculate($event, $this->school->id);

        return response()->json(['data' => $registration->load(['event', 'item', 'participants'])], 201);
    }
}
