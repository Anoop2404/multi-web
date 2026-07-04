<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\Student;
use App\Services\Events\FestBulkRegistrationService;
use App\Services\Events\FestEventRegistrationService;
use Illuminate\Http\Request;

class FestEventStudentRegistrationController extends SchoolAdminController
{
    public function index(string $tenantId, string $program, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $service = app(FestEventRegistrationService::class);

        return response()->json([
            'event_registrations' => $service->studentEventRegistrations($event, $this->school->id),
            'require_event_registration' => $service->requireEventRegistration($event),
            'event_registration_open' => $service->isEventRegistrationOpen($event),
        ]);
    }

    public function store(Request $request, string $tenantId, string $program, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $data = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
        ]);

        $service = app(FestEventRegistrationService::class);
        $count = $service->registerStudents($event, $this->school, $data['student_ids']);

        return back()->with('success', "Registered {$count} student(s) for the event.");
    }

    public function bulkAssign(Request $request, string $tenantId, string $program, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $data = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'exists:fest_event_items,id',
            'standbys_by_item' => 'nullable|array',
        ]);

        $result = app(FestBulkRegistrationService::class)->assignStudentsToItems(
            $event,
            $this->school,
            $data['student_ids'],
            $data['item_ids'],
            $data['standbys_by_item'] ?? [],
        );

        $message = "Created {$result['created']} registration(s).";
        if ($result['errors'] !== []) {
            $message .= ' Some rows failed: '.implode(' ', array_slice($result['errors'], 0, 3));
        }

        return back()->with($result['errors'] === [] ? 'success' : 'warning', $message);
    }
}
