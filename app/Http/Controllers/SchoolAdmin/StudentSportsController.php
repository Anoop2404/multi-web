<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Concerns\DownloadsStudentFestIdCard;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestRegistration;
use App\Models\Student;
use App\Services\Events\FestBulkRegistrationService;
use App\Services\Events\FestEventRegistrationService;
use App\Services\Events\FestItemRegistrationGate;
use App\Services\Events\FestRegistrationEligibilityService;
use Illuminate\Http\Request;

class StudentSportsController extends SchoolAdminController
{
    use DownloadsStudentFestIdCard;

    public function registerSportsEvent(string $tenantId, Student $student, FestEvent $event)
    {
        abort_if($student->tenant_id !== $this->school->id, 403);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        abort_if($event->event_type !== 'sports', 404);

        if ($this->school->fest_registration_closed) {
            return back()->with('error', 'Fest registration is closed for your school.');
        }

        app(FestEventRegistrationService::class)->registerStudent($event, $student, $this->school);

        return back()->with('success', "Registered {$student->name} for {$event->title}.");
    }

    public function registerSportsItems(Request $request, string $tenantId, Student $student, FestEvent $event)
    {
        abort_if($student->tenant_id !== $this->school->id, 403);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        abort_if($event->event_type !== 'sports', 404);

        if ($this->school->fest_registration_closed) {
            return back()->with('error', 'Fest registration is closed for your school.');
        }

        $data = $request->validate([
            'item_ids'   => 'required|array|min:1',
            'item_ids.*' => 'integer|exists:fest_event_items,id',
        ]);

        $result = app(FestBulkRegistrationService::class)->assignStudentsToItems(
            $event,
            $this->school,
            [$student->id],
            $data['item_ids'],
        );

        if ($result['created'] === 0 && $result['errors'] !== []) {
            return back()->with('error', implode(' ', array_slice($result['errors'], 0, 3)));
        }

        $message = "Registered {$student->name} for {$result['created']} item(s).";
        if ($result['errors'] !== []) {
            $message .= ' Some items failed: '.implode(' ', array_slice($result['errors'], 0, 2));
        }

        return back()->with($result['created'] > 0 ? 'success' : 'warning', $message);
    }

    public function eligibleSportsItems(string $tenantId, Student $student, FestEvent $event)
    {
        abort_if($student->tenant_id !== $this->school->id, 403);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        abort_if($event->event_type !== 'sports', 404);

        $student->load('schoolClass');

        $eligibility = app(FestRegistrationEligibilityService::class);
        $itemGate = app(FestItemRegistrationGate::class);

        $registeredItemIds = FestRegistration::query()
            ->where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->active()
            ->whereHas('participants', fn ($q) => $q
                ->where('student_id', $student->id)
                ->where('participant_role', 'performer'))
            ->pluck('item_id')
            ->all();

        $items = FestEventItem::query()
            ->where('event_id', $event->id)
            ->where('is_enabled', true)
            ->with('head:id,name')
            ->orderBy('title')
            ->get(['id', 'title', 'head_id', 'sport_discipline', 'participant_type', 'age_group']);

        $rows = $items->map(function (FestEventItem $item) use ($event, $student, $eligibility, $itemGate, $registeredItemIds) {
            if (in_array($item->id, $registeredItemIds, true)) {
                return [
                    'id'                => $item->id,
                    'title'             => $item->title,
                    'head_name'         => $item->head?->name,
                    'sport_discipline'  => $item->sport_discipline,
                    'age_group'         => $item->age_group,
                    'participant_type'  => $item->participant_type,
                    'registration_open' => $itemGate->isOpen($item),
                    'eligible'          => false,
                    'already_registered'=> true,
                    'reason'            => 'Already registered',
                ];
            }

            $errors = $eligibility->validateStudent($student, $event, $item);
            $open = $itemGate->isOpen($item);

            return [
                'id'                => $item->id,
                'title'             => $item->title,
                'head_name'         => $item->head?->name,
                'sport_discipline'  => $item->sport_discipline,
                'age_group'         => $item->age_group,
                'participant_type'  => $item->participant_type,
                'registration_open' => $open,
                'eligible'          => $open && $errors === [],
                'already_registered'=> false,
                'reason'            => ! $open ? 'Registration closed for this item' : ($errors[0] ?? null),
            ];
        })->values();

        return response()->json(['items' => $rows]);
    }

    public function festIdCard(Request $request, string $tenantId, Student $student, FestEvent $event)
    {
        return $this->studentFestIdCardResponse($request, $event, $student, $this->school);
    }
}
