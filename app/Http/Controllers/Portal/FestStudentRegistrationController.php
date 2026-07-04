<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\Tenant;
use App\Services\Events\FestEventRegistrationService;
use App\Services\Events\FestRegistrationCreateService;
use Illuminate\Http\Request;

class FestStudentRegistrationController extends Controller
{
    public function index(Request $request, string $tenantId)
    {
        $student = $request->attributes->get('portalStudent');
        $school = Tenant::findOrFail($tenantId);
        abort_if($student->tenant_id !== $school->id, 403);

        $sahodayaId = $school->parent_id;
        $regService = app(FestEventRegistrationService::class);

        $events = FestEvent::where('tenant_id', $sahodayaId)
            ->whereIn('status', ['published', 'registration_open', 'ongoing'])
            ->where('allow_student_self_register', true)
            ->orderByDesc('event_start')
            ->get()
            ->map(function (FestEvent $event) use ($regService, $student) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'event_type' => $event->event_type,
                    'registered' => $regService->studentIsRegistered($event, $student->id),
                    'registration_open' => $regService->isEventRegistrationOpen($event),
                    'items' => $regService->studentItemRegistrations($event, $student->id),
                ];
            });

        return inertia('Portal/Student/FestRegistrations', [
            'school' => $school->only('id', 'name'),
            'student' => $student->only('id', 'name', 'reg_no'),
            'events' => $events,
        ]);
    }

    public function registerEvent(Request $request, string $tenantId, FestEvent $event)
    {
        $student = $request->attributes->get('portalStudent');
        $school = Tenant::findOrFail($tenantId);
        abort_if($student->tenant_id !== $school->id, 403);
        abort_if($event->tenant_id !== $school->parent_id, 403);
        abort_if(! $event->allow_student_self_register, 403);

        app(FestEventRegistrationService::class)->registerStudent($event, $student, $school);

        return back()->with('success', 'Registered for '.$event->title.'.');
    }

    public function registerItem(Request $request, string $tenantId, FestEvent $event, FestEventItem $item)
    {
        $student = $request->attributes->get('portalStudent');
        $school = Tenant::findOrFail($tenantId);
        abort_if($student->tenant_id !== $school->id, 403);
        abort_if($event->tenant_id !== $school->parent_id, 403);
        abort_if($item->event_id !== $event->id, 403);
        abort_if(! $event->allow_student_self_register, 403);

        app(FestRegistrationCreateService::class)->createForSchool(
            $event,
            $item,
            $school,
            [$student->id],
        );

        return back()->with('success', 'Registered for '.$item->title.'.');
    }
}
