<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestRegistration;
use App\Models\Tenant;
use App\Services\Events\FestEventRegistrationService;
use App\Services\Events\FestItemRegistrationGate;
use App\Services\Events\FestRegistrationCreateService;
use App\Services\Events\FestRegistrationEligibilityService;
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
            ->map(function (FestEvent $event) use ($regService, $student, $school) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'event_type' => $event->event_type,
                    'registered' => $regService->studentIsRegistered($event, $student->id),
                    'registration_open' => $regService->isEventRegistrationOpen($event),
                    'require_event_registration' => $regService->requireEventRegistration($event),
                    'items' => $regService->studentItemRegistrations($event, $student->id),
                    'fee_blocks_items' => false,
                    'school_registration_closed' => (bool) $school->fest_registration_closed,
                ];
            });

        return inertia('Portal/Student/FestRegistrations', [
            'school' => $school->only('id', 'name'),
            'student' => $student->only('id', 'name', 'reg_no'),
            'events' => $events,
        ]);
    }

    public function eligibleItems(Request $request, string $tenantId, FestEvent $event)
    {
        $student = $request->attributes->get('portalStudent');
        $school = Tenant::findOrFail($tenantId);
        abort_if($student->tenant_id !== $school->id, 403);
        abort_if($event->tenant_id !== $school->parent_id, 403);
        abort_if(! $event->allow_student_self_register, 403);
        abort_if($event->event_type !== 'sports', 404);

        $student->load('schoolClass');

        $eligibility = app(FestRegistrationEligibilityService::class);
        $itemGate = app(FestItemRegistrationGate::class);

        $registeredItemIds = FestRegistration::query()
            ->where('event_id', $event->id)
            ->where('school_id', $school->id)
            ->active()
            ->whereHas('participants', fn ($q) => $q
                ->where('student_id', $student->id)
                ->where('participant_role', 'performer'))
            ->pluck('item_id')
            ->all();

        $items = FestEventItem::query()
            ->where('event_id', $event->id)
            ->where('is_enabled', true)
            ->where('participant_type', 'individual')
            ->with('head:id,name')
            ->orderBy('title')
            ->get(['id', 'title', 'head_id', 'sport_discipline', 'participant_type', 'age_group']);

        $rows = $items->map(function (FestEventItem $item) use ($event, $student, $eligibility, $itemGate, $registeredItemIds) {
            if (in_array($item->id, $registeredItemIds, true)) {
                return [
                    'id'                 => $item->id,
                    'title'              => $item->title,
                    'head_name'          => $item->head?->name,
                    'sport_discipline'   => $item->sport_discipline,
                    'age_group'          => $item->age_group,
                    'registration_open'  => $itemGate->isOpen($item),
                    'eligible'           => false,
                    'already_registered' => true,
                    'reason'             => 'Already registered',
                ];
            }

            $errors = $eligibility->validateStudent($student, $event, $item);
            $open = $itemGate->isOpen($item);

            return [
                'id'                 => $item->id,
                'title'              => $item->title,
                'head_name'          => $item->head?->name,
                'sport_discipline'   => $item->sport_discipline,
                'age_group'          => $item->age_group,
                'registration_open'  => $open,
                'eligible'           => $open && $errors === [],
                'already_registered' => false,
                'reason'             => ! $open ? 'Registration closed for this item' : ($errors[0] ?? null),
            ];
        })->values();

        return response()->json(['items' => $rows]);
    }

    public function registerEvent(Request $request, string $tenantId, FestEvent $event)
    {
        $student = $request->attributes->get('portalStudent');
        $school = Tenant::findOrFail($tenantId);
        abort_if($student->tenant_id !== $school->id, 403);
        abort_if($event->tenant_id !== $school->parent_id, 403);
        abort_if(! $event->allow_student_self_register, 403);
        abort_if($school->fest_registration_closed, 422, 'Fest registration is closed for your school.');

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
        abort_if($school->fest_registration_closed, 422, 'Fest registration is closed for your school.');

        app(FestRegistrationCreateService::class)->createForSchool(
            $event,
            $item,
            $school,
            [$student->id],
        );

        return back()->with('success', 'Registered for '.$item->title.'.');
    }
}
