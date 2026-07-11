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
use App\Services\Events\Concerns\HandlesFestRegistrationDuplicates;
use App\Services\Events\EventLifecycleGate;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class FestRegistrationCreateService
{
    use HandlesFestRegistrationDuplicates;

    /**
     * @param  list<int>  $performerIds
     * @param  list<int>  $standbyIds
     * @param  array{coach_name?: ?string, coach_phone?: ?string, manager_name?: ?string, manager_phone?: ?string}|null  $teamContacts
     */
    public function createForSchool(
        FestEvent $event,
        FestEventItem $item,
        Tenant $school,
        array $performerIds,
        array $standbyIds = [],
        ?string $teamName = null,
        bool $skipSchoolClosedCheck = false,
        ?array $teamContacts = null,
    ): FestRegistration {
        abort_if($school->parent_id !== $event->tenant_id, 403);
        abort_if($item->event_id !== $event->id, 403);

        app(FestRegionPartitionService::class)->assertRegionSelected($event, $school);

        $router = app(FestRegistrationRouterService::class);
        $targetEvent = $router->resolveTargetEvent($event, $item, $school->id);
        if ($targetEvent->id !== $event->id) {
            $item = FestEventItem::where('event_id', $targetEvent->id)
                ->where(function ($q) use ($item) {
                    $q->where('inherited_from_item_id', $item->id)
                        ->orWhere('item_code', $item->item_code);
                })
                ->firstOrFail();
            $event = $targetEvent;
        }

        app(FestEventRegistrationService::class)->assertSchoolMembershipApproved($school);
        abort_if($item->is_enabled === false, 422, 'This item is not open for registration.');

        app(FestItemRegistrationGate::class)->assertOpen($item);
        app(FestRegistrationFeeGate::class)->assertCanRegister($event, $school);

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

        try {
            return DB::transaction(function () use ($event, $item, $school, $performerIds, $standbyIds, $teamName, $isGroup, $teamContacts) {
                $eventRegService = app(FestEventRegistrationService::class);
                foreach (array_merge($performerIds, $standbyIds) as $studentId) {
                    if ($eventRegService->requireEventRegistration($event) && $event->event_type !== 'sports') {
                        $eventRegService->assertStudentEligible($event, $studentId);
                    } else {
                        $student = Student::find($studentId);
                        if ($student) {
                            $eventRegService->registerStudent($event, $student, $school);
                        }
                    }
                }

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
                        'coach_name'      => filled($teamContacts['coach_name'] ?? null) ? trim((string) $teamContacts['coach_name']) : null,
                        'coach_phone'     => filled($teamContacts['coach_phone'] ?? null) ? trim((string) $teamContacts['coach_phone']) : null,
                        'manager_name'    => filled($teamContacts['manager_name'] ?? null) ? trim((string) $teamContacts['manager_name']) : null,
                        'manager_phone'   => filled($teamContacts['manager_phone'] ?? null) ? trim((string) $teamContacts['manager_phone']) : null,
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

                foreach ($registration->fresh(['participants'])->participants as $participant) {
                    app(FestNumberingService::class)->assignParticipantNumbers($participant);
                }

                app(FestSchoolEventFeeService::class)->recalculate($event, $school->id);

                return $registration->load(['participants.student', 'item']);
            });
        } catch (QueryException $e) {
            $this->abortOnFestRegistrationDuplicate($e);

            throw $e;
        }
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

        try {
            return DB::transaction(function () use ($event, $item, $school, $teacherIds) {
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
            });
        } catch (QueryException $e) {
            $this->abortOnFestRegistrationDuplicate($e);

            throw $e;
        }
    }
}
