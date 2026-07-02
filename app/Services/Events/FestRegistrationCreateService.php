<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestGroup;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Services\Events\EventLifecycleGate;

class FestRegistrationCreateService
{
    /**
     * @param  list<int>  $performerIds
     * @param  list<int>  $standbyIds
     */
    public function createForSchool(
        FestEvent $event,
        FestEventItem $item,
        Tenant $school,
        array $performerIds,
        array $standbyIds = [],
        ?string $teamName = null,
        bool $skipSchoolClosedCheck = false,
    ): FestRegistration {
        abort_if($school->parent_id !== $event->tenant_id, 403);
        abort_if($item->event_id !== $event->id, 403);
        abort_if($item->is_enabled === false, 422, 'This item is not open for registration.');

        if (! $skipSchoolClosedCheck && $school->fest_registration_closed) {
            abort(422, 'Fest registration is closed for this school.');
        }

        if ($event->registration_locked) {
            abort(422, 'Registration is locked for this event.');
        }

        abort_if(! $event->isRegistrationOpen(), 422, 'Registration is closed for this event.');
        EventLifecycleGate::allowRegistration($event);

        if ($event->event_type === 'teacher_fest') {
            return $this->createTeacherRegistration($event, $item, $school, $performerIds);
        }

        $standbyIds = array_values(array_unique($standbyIds));
        $performerIds = array_values(array_diff(array_unique($performerIds), $standbyIds));

        abort_if($performerIds === [], 422, 'Select at least one participant.');

        $isGroup = in_array($item->participant_type, ['group', 'team'], true);
        if ($isGroup) {
            abort_if(! filled($teamName), 422, 'Team name is required for group items.');
            $error = $item->validateSquadCount(count($performerIds));
            abort_if($error, 422, $error);
        } elseif (count($performerIds) > 1) {
            abort(422, 'This item allows only one participant.');
        }

        $limitErrors = (new FestParticipationLimitService($event))
            ->validateRegistration($item, $school->id, $performerIds, $standbyIds);
        abort_if($limitErrors, 422, implode(' ', $limitErrors));

        $eligibilityErrors = app(FestRegistrationEligibilityService::class)
            ->validateStudents($event, $item, array_merge($performerIds, $standbyIds));
        abort_if($eligibilityErrors, 422, implode(' ', $eligibilityErrors));

        $registration = FestRegistration::create([
            'event_id'     => $event->id,
            'item_id'      => $item->id,
            'school_id'    => $school->id,
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        $groupId = null;
        if ($isGroup) {
            $group = FestGroup::create([
                'registration_id' => $registration->id,
                'team_name'       => $teamName,
            ]);
            $groupId = $group->id;
        }

        foreach ($performerIds as $studentId) {
            abort_if(Student::where('id', $studentId)->where('tenant_id', $school->id)->doesntExist(), 403);
            FestParticipant::create([
                'registration_id'  => $registration->id,
                'group_id'         => $groupId,
                'student_id'       => $studentId,
                'participant_type' => 'student',
                'participant_role' => 'performer',
            ]);
        }

        foreach ($standbyIds as $studentId) {
            abort_if(Student::where('id', $studentId)->where('tenant_id', $school->id)->doesntExist(), 403);
            FestParticipant::create([
                'registration_id'  => $registration->id,
                'group_id'         => $groupId,
                'student_id'       => $studentId,
                'participant_type' => 'student',
                'participant_role' => 'standby',
            ]);
        }

        app(FestLevelRegistrationService::class)->syncRegistration($registration->fresh(['participants']));

        app(FestSchoolEventFeeService::class)->recalculate($event, $school->id);

        return $registration->load(['participants.student', 'item']);
    }

    /** @param  list<int>  $teacherIds */
    private function createTeacherRegistration(
        FestEvent $event,
        FestEventItem $item,
        Tenant $school,
        array $teacherIds,
    ): FestRegistration {
        $teacherIds = array_values(array_unique($teacherIds));
        abort_if($teacherIds === [], 422, 'Select at least one teacher.');

        if (count($teacherIds) > 1 && ! in_array($item->participant_type, ['group', 'team'], true)) {
            abort(422, 'This item allows only one teacher.');
        }

        $registration = FestRegistration::create([
            'event_id'     => $event->id,
            'item_id'      => $item->id,
            'school_id'    => $school->id,
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        foreach ($teacherIds as $teacherId) {
            abort_if(Teacher::where('id', $teacherId)->where('tenant_id', $school->id)->doesntExist(), 403);
            FestParticipant::create([
                'registration_id'  => $registration->id,
                'teacher_id'       => $teacherId,
                'participant_type' => 'teacher',
                'participant_role' => 'performer',
            ]);
        }

        app(FestLevelRegistrationService::class)->syncRegistration($registration->fresh(['participants']));

        app(FestSchoolEventFeeService::class)->recalculate($event, $school->id);

        return $registration->load(['participants.teacher', 'item']);
    }
}
