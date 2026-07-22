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
use Illuminate\Validation\ValidationException;

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
        if ($item->is_enabled === false) {
            throw ValidationException::withMessages(['registration' => 'This item is not open for registration.']);
        }

        app(FestItemRegistrationGate::class)->assertOpen($item);
        app(FestRegistrationFeeGate::class)->assertCanRegister($event, $school);

        if (! $skipSchoolClosedCheck && $school->fest_registration_closed) {
            throw ValidationException::withMessages(['registration' => 'Fest registration is closed for this school.']);
        }

        if ($event->registration_locked) {
            throw ValidationException::withMessages(['registration' => 'Registration is locked for this event.']);
        }

        if (! $event->isRegistrationOpen()) {
            throw ValidationException::withMessages(['registration' => 'Registration is closed for this event.']);
        }
        EventLifecycleGate::allowRegistration($event);

        if ($event->event_type === 'teacher_fest') {
            return $this->createTeacherRegistration($event, $item, $school, $performerIds);
        }

        $standbyIds = array_values(array_unique($standbyIds));
        $performerIds = array_values(array_diff(array_unique($performerIds), $standbyIds));

        if ($performerIds === []) {
            throw ValidationException::withMessages(['student_ids' => 'Select at least one participant.']);
        }

        $isGroup = in_array($item->participant_type, ['group', 'team'], true);
        if ($isGroup) {
            if (! filled($teamName)) {
                $teamName = $this->nextDefaultTeamName($event, $item, $school);
            }
            $error = $item->validateSquadCount(count($performerIds));
            if ($error) {
                throw ValidationException::withMessages(['student_ids' => $error]);
            }
        } elseif (count($performerIds) > 1) {
            throw ValidationException::withMessages(['student_ids' => 'This item allows only one participant.']);
        }

        $limitErrors = (new FestParticipationLimitService($event))
            ->validateRegistration($item, $school->id, $performerIds, $standbyIds);
        if ($limitErrors) {
            throw ValidationException::withMessages(['student_ids' => implode(' ', $limitErrors)]);
        }

        $eligibilityErrors = app(FestRegistrationEligibilityService::class)
            ->validateStudents($event, $item, array_merge($performerIds, $standbyIds));
        if ($eligibilityErrors) {
            throw ValidationException::withMessages(['student_ids' => implode(' ', $eligibilityErrors)]);
        }

        $item->loadMissing('head');
        $limitService = new FestParticipationLimitService($event);
        $waitlisted = $event->event_type === 'sports' && $limitService->isHeadAtCapacity($item, $school->id);
        $initialStatus = match (true) {
            $waitlisted => 'waitlisted',
            $item->head?->requiresManualApproval() => 'pending_approval',
            default => 'submitted',
        };

        try {
            return DB::transaction(function () use ($event, $item, $school, $performerIds, $standbyIds, $teamName, $isGroup, $teamContacts, $initialStatus) {
                $eventRegService = app(FestEventRegistrationService::class);
                foreach (array_merge($performerIds, $standbyIds) as $studentId) {
                    if ($eventRegService->requireEventRegistration($event)) {
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
                    'status'       => $initialStatus,
                    'submitted_at' => $initialStatus === 'waitlisted' ? null : now(),
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

                if ($initialStatus !== 'waitlisted') {
                    foreach ($registration->fresh(['participants'])->participants as $participant) {
                        app(FestNumberingService::class)->assignParticipantNumbers($participant);
                    }

                    app(FestSchoolEventFeeService::class)->recalculate($event, $school->id);
                }

                return $registration->load(['participants.student', 'item']);
            });
        } catch (QueryException $e) {
            $this->abortOnFestRegistrationDuplicate($e);

            throw $e;
        }
    }

    /**
     * Edit the roster of an already-submitted registration in place, instead of
     * withdrawing and re-registering. Re-runs the same squad/eligibility/quota
     * validation as createForSchool(), but excludes this registration's own current
     * participants from the "already has an entry" / per-school / per-student quota
     * counts (otherwise every edit would immediately trip its own quota).
     *
     * @param  list<int>  $performerIds
     * @param  list<int>  $standbyIds
     * @param  array{coach_name?: ?string, coach_phone?: ?string, manager_name?: ?string, manager_phone?: ?string}|null  $teamContacts
     */
    public function updateForSchool(
        FestRegistration $registration,
        FestEvent $event,
        FestEventItem $item,
        Tenant $school,
        array $performerIds,
        array $standbyIds = [],
        ?string $teamName = null,
        ?array $teamContacts = null,
    ): FestRegistration {
        abort_if($registration->event_id !== $event->id, 403);
        abort_if($registration->item_id !== $item->id, 403, 'Cannot change which item a registration belongs to — cancel and re-register instead.');
        abort_if($registration->school_id !== $school->id, 403);
        abort_if($school->parent_id !== $event->tenant_id, 403);

        if (! app(FestRegistrationService::class)->canSchoolCancel($registration, $event)) {
            throw ValidationException::withMessages([
                'registration' => 'This registration can no longer be edited — it may already be approved with payment, past results-publish, or the event has closed.',
            ]);
        }

        if ($item->is_enabled === false) {
            throw ValidationException::withMessages(['registration' => 'This item is not open for registration.']);
        }
        app(FestItemRegistrationGate::class)->assertOpen($item);
        if ($event->schedule_published) {
            throw ValidationException::withMessages([
                'registration' => 'The squad cannot be changed once the fest-day schedule has been published.',
            ]);
        }

        if ($event->event_type === 'teacher_fest') {
            return $this->updateTeacherRegistration($registration, $event, $item, $school, $performerIds);
        }

        $standbyIds = array_values(array_unique($standbyIds));
        $performerIds = array_values(array_diff(array_unique($performerIds), $standbyIds));

        if ($performerIds === []) {
            throw ValidationException::withMessages(['student_ids' => 'Select at least one participant.']);
        }

        $isGroup = in_array($item->participant_type, ['group', 'team'], true);
        if ($isGroup) {
            if (! filled($teamName)) {
                $teamName = $this->nextDefaultTeamName($event, $item, $school, $registration->id);
            }
            $error = $item->validateSquadCount(count($performerIds));
            if ($error) {
                throw ValidationException::withMessages(['student_ids' => $error]);
            }
        } elseif (count($performerIds) > 1) {
            throw ValidationException::withMessages(['student_ids' => 'This item allows only one participant.']);
        }

        $limitErrors = (new FestParticipationLimitService($event))
            ->validateRegistration($item, $school->id, $performerIds, $standbyIds, $registration->id);
        if ($limitErrors) {
            throw ValidationException::withMessages(['student_ids' => implode(' ', $limitErrors)]);
        }

        $eligibilityErrors = app(FestRegistrationEligibilityService::class)
            ->validateStudents($event, $item, array_merge($performerIds, $standbyIds));
        if ($eligibilityErrors) {
            throw ValidationException::withMessages(['student_ids' => implode(' ', $eligibilityErrors)]);
        }

        return DB::transaction(function () use ($registration, $event, $item, $school, $performerIds, $standbyIds, $teamName, $isGroup, $teamContacts) {
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

            // Clear the old roster (and its numbering) before rebuilding it — the fest
            // level registration number is per-student/per-event, not per-participant
            // row, so re-syncing afterwards correctly reuses/reissues as needed.
            $registration->participants()->delete();

            $groupId = null;
            if ($isGroup) {
                $group = FestGroup::updateOrCreate(
                    ['registration_id' => $registration->id],
                    [
                        'team_name'     => $teamName,
                        'coach_name'    => filled($teamContacts['coach_name'] ?? null) ? trim((string) $teamContacts['coach_name']) : null,
                        'coach_phone'   => filled($teamContacts['coach_phone'] ?? null) ? trim((string) $teamContacts['coach_phone']) : null,
                        'manager_name'  => filled($teamContacts['manager_name'] ?? null) ? trim((string) $teamContacts['manager_name']) : null,
                        'manager_phone' => filled($teamContacts['manager_phone'] ?? null) ? trim((string) $teamContacts['manager_phone']) : null,
                    ],
                );
                $groupId = $group->id;
            } else {
                FestGroup::where('registration_id', $registration->id)->delete();
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

            // Editing the approved roster is a material change — send it back through
            // Sahodaya review rather than silently keeping the old approval.
            if ($registration->status === 'approved') {
                $registration->update(['status' => 'submitted', 'submitted_at' => now()]);
            }

            app(FestLevelRegistrationService::class)->syncRegistration($registration->fresh(['participants']));

            foreach ($registration->fresh(['participants'])->participants as $participant) {
                app(FestNumberingService::class)->assignParticipantNumbers($participant);
            }

            app(FestSchoolEventFeeService::class)->recalculate($event, $school->id);

            return $registration->fresh(['participants.student', 'item']);
        });
    }

    /**
     * Default "Team N" name for a group/team item — the school's Nth entry under this item,
     * so a Sahodaya/school admin never has to type a team name to register directly. Still
     * overridable: callers only reach here when the submitted team_name was blank.
     */
    private function nextDefaultTeamName(FestEvent $event, FestEventItem $item, Tenant $school, ?int $excludeRegistrationId = null): string
    {
        $count = FestRegistration::where('event_id', $event->id)
            ->where('item_id', $item->id)
            ->where('school_id', $school->id)
            ->whereIn('status', ['submitted', 'pending_approval', 'waitlisted', 'approved'])
            ->when($excludeRegistrationId, fn ($q) => $q->where('id', '!=', $excludeRegistrationId))
            ->count();

        return 'Team '.($count + 1);
    }

    /** @param  list<int>  $teacherIds */
    private function updateTeacherRegistration(
        FestRegistration $registration,
        FestEvent $event,
        FestEventItem $item,
        Tenant $school,
        array $teacherIds,
    ): FestRegistration {
        $teacherIds = array_values(array_unique($teacherIds));
        if ($teacherIds === []) {
            throw ValidationException::withMessages(['teacher_ids' => 'Select at least one teacher.']);
        }

        if (count($teacherIds) > 1 && ! in_array($item->participant_type, ['group', 'team'], true)) {
            throw ValidationException::withMessages(['teacher_ids' => 'This item allows only one teacher.']);
        }

        return DB::transaction(function () use ($registration, $event, $school, $teacherIds) {
            $registration->participants()->delete();

            foreach ($teacherIds as $teacherId) {
                abort_if(Teacher::where('id', $teacherId)->where('tenant_id', $school->id)->doesntExist(), 403);
                FestParticipant::create([
                    'registration_id'  => $registration->id,
                    'teacher_id'       => $teacherId,
                    'participant_type' => 'teacher',
                    'participant_role' => 'performer',
                ]);
            }

            if ($registration->status === 'approved') {
                $registration->update(['status' => 'submitted', 'submitted_at' => now()]);
            }

            app(FestLevelRegistrationService::class)->syncRegistration($registration->fresh(['participants']));
            app(FestSchoolEventFeeService::class)->recalculate($event, $school->id);

            return $registration->fresh(['participants.teacher', 'item']);
        });
    }

    /** @param  list<int>  $teacherIds */
    private function createTeacherRegistration(
        FestEvent $event,
        FestEventItem $item,
        Tenant $school,
        array $teacherIds,
    ): FestRegistration {
        $teacherIds = array_values(array_unique($teacherIds));
        if ($teacherIds === []) {
            throw ValidationException::withMessages(['teacher_ids' => 'Select at least one teacher.']);
        }

        if (count($teacherIds) > 1 && ! in_array($item->participant_type, ['group', 'team'], true)) {
            throw ValidationException::withMessages(['teacher_ids' => 'This item allows only one teacher.']);
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
