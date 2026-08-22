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
use App\Support\FestTeamSquadRules;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

        if (! $event->rootEvent()->usesPhasedRegionalBilling()) {
            app(FestRegionPartitionService::class)->assertRegionSelected($event, $school);
        }

        $router = app(FestRegistrationRouterService::class);
        $targetEvent = $router->resolveTargetEvent($event, $item, $school->id);
        if ($targetEvent->id !== $event->id) {
            // Region children are infrastructure behind the school-facing hub. Older
            // children were left in draft even after the hub opened registration.
            app(FestRegionPartitionService::class)
                ->inheritRegistrationLifecycle($event, $targetEvent);

            // Refresh the regional copy from the hub before validating it. Legacy
            // copies can carry old category keys such as CATEGORY__II.
            $targetItem = app(FestItemSyncService::class)->copyItemToPartition(
                $event,
                $item,
                $targetEvent,
                $targetEvent->partition_role ?? 'region',
            );

            if (! $targetItem) {
                throw ValidationException::withMessages([
                    'registration' => 'This item is not configured for your region. Ask the Sahodaya administrator to sync regional items.',
                ]);
            }

            $item = $targetItem;
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

        try {
            EventLifecycleGate::allowRegistrationForItem($event, $item);
        } catch (HttpException $e) {
            throw ValidationException::withMessages(['registration' => $e->getMessage()]);
        }

        if ($event->event_type === 'teacher_fest') {
            return $this->createTeacherRegistration($event, $item, $school, $performerIds);
        }

        $standbyIds = array_values(array_unique($standbyIds));
        $performerIds = array_values(array_diff(array_unique($performerIds), $standbyIds));

        if ($performerIds === []) {
            throw ValidationException::withMessages(['student_ids' => 'Select at least one participant.']);
        }

        $isGroup = FestTeamSquadRules::isMultiPerson($item->participant_type);
        if ($isGroup) {
            if (! filled($teamName)) {
                $teamName = $this->nextDefaultTeamName($event, $item, $school);
            }
            $error = $item->validateSquadCount(count($performerIds));
            if ($error) {
                throw ValidationException::withMessages(['student_ids' => $error]);
            }
        } else {
            $maxAllowed = (int) ($item->max_per_school ?? 1);
            if (count($performerIds) > $maxAllowed) {
                throw ValidationException::withMessages(['student_ids' => "Maximum {$maxAllowed} participant".($maxAllowed === 1 ? '' : 's').' allowed for this item.']);
            }

            $existingReg = FestRegistration::whereIn('event_id', $event->reportableEventIds())
                ->where('school_id', $school->id)
                ->where('item_id', $item->id)
                ->whereIn('status', ['submitted', 'pending_approval', 'approved'])
                ->first();

            if ($existingReg) {
                return $this->updateForSchool(
                    $existingReg,
                    $event,
                    $item,
                    $school,
                    $performerIds,
                    $standbyIds,
                    $teamName,
                    $teamContacts,
                );
            }
        }

        $item->loadMissing('head');

        try {
            return DB::transaction(function () use ($event, $item, $school, $performerIds, $standbyIds, $teamName, $isGroup, $teamContacts) {
                // Quota and eligibility checks are inside the transaction with lockForUpdate
                // to prevent race conditions where concurrent requests overflow per-school quotas.
                \App\Models\FestEvent::query()->whereKey($event->id)->lockForUpdate()->first();

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

                $limitService = new FestParticipationLimitService($event);
                $waitlisted = $event->event_type === 'sports' && $limitService->isHeadAtCapacity($item, $school->id);

                $policy = app(FestParticipationPolicyService::class)->resolveForEvent($event);
                $feeService = app(FestSchoolEventFeeService::class);
                $feeRequiredBeforeApproval = ($policy['require_fee_before_approval'] ?? false) && $feeService->feeRequired($event);

                $registrationDraft = new FestRegistration([
                    'event_id'  => $event->id,
                    'item_id'   => $item->id,
                    'school_id' => $school->id,
                ]);
                $registrationDraft->setRelation('item', $item);

                $feeUnpaid = $feeRequiredBeforeApproval && ! $feeService->isPaidForRegistration($event, $registrationDraft);

                $initialStatus = match (true) {
                    $waitlisted => 'waitlisted',
                    $item->head?->requiresManualApproval() || $event->requiresManualApproval() => 'pending_approval',
                    $feeUnpaid => 'submitted',
                    default => 'approved',
                };
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
                    'status'       => 'submitted',
                    'submitted_at' => $waitlisted ? null : now(),
                ]);

                if ($event->rootEvent()->usesPhasedRegionalBilling() && $item->phase) {
                    app(FestSchoolPhaseRegionService::class)
                        ->lockForRegistration($event, $item->phase, $school->id);
                }

                $groupId = null;
                if (FestTeamSquadRules::isMultiPerson($item->participant_type)) {
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

                // One query for the whole roster instead of one per student — performerIds and
                // standbyIds are already deduped and disjoint (see array_diff/array_unique above),
                // so the requested-id count and the matched-row count are directly comparable.
                // Whole method still runs inside the outer DB::transaction, so this aborting still
                // rolls back the registration/group rows created above exactly as the old
                // per-student abort_if() did — same all-or-nothing behavior, fewer queries.
                $rosterIds = array_merge($performerIds, $standbyIds);
                $validRosterCount = Student::whereIn('id', $rosterIds)->where('tenant_id', $school->id)->count();
                abort_if($validRosterCount !== count($rosterIds), 403);

                foreach ($performerIds as $studentId) {
                    FestParticipant::create([
                        'registration_id'  => $registration->id,
                        'group_id'         => $groupId,
                        'student_id'       => $studentId,
                        'participant_type' => 'student',
                        'participant_role' => 'performer',
                    ]);
                }

                foreach ($standbyIds as $studentId) {
                    FestParticipant::create([
                        'registration_id'  => $registration->id,
                        'group_id'         => $groupId,
                        'student_id'       => $studentId,
                        'participant_type' => 'student',
                        'participant_role' => 'standby',
                    ]);
                }

                app(FestLevelRegistrationService::class)->syncRegistration($registration->fresh(['participants']));

                if (! $waitlisted) {
                    foreach ($registration->fresh(['participants'])->participants as $participant) {
                        app(FestNumberingService::class)->assignParticipantNumbers($participant);
                    }

                    app(FestSchoolEventFeeService::class)->recalculate($event, $school->id);
                }

                $policy = app(FestParticipationPolicyService::class)->resolveForEvent($event);
                $feeService = app(FestSchoolEventFeeService::class);
                $feeRequiredBeforeApproval = ($policy['require_fee_before_approval'] ?? false) && $feeService->feeRequired($event);
                $feeUnpaid = $feeRequiredBeforeApproval && ! $feeService->isPaidForRegistration($event, $registration);

                $finalStatus = match (true) {
                    $waitlisted => 'waitlisted',
                    $item->head?->requiresManualApproval() || $event->requiresManualApproval() => 'pending_approval',
                    $feeUnpaid => 'submitted',
                    default => 'approved',
                };

                if ($registration->status !== $finalStatus) {
                    $registration->update([
                        'status'       => $finalStatus,
                        'submitted_at' => $finalStatus === 'waitlisted' ? null : ($registration->submitted_at ?? now()),
                    ]);
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
        // Defense-in-depth, same reasoning as FestRegistrationService::cancel(): a
        // partitioned hub's registrations live on the school's region child, not the hub.
        abort_unless(in_array($registration->event_id, $event->reportableEventIds(), true), 403);
        abort_if($registration->item_id !== $item->id, 403, 'Cannot change which item a registration belongs to — cancel and re-register instead.');
        abort_if($registration->school_id !== $school->id, 403);
        abort_if($school->parent_id !== $event->tenant_id, 403);

        if (! app(FestRegistrationService::class)->canSchoolEditRoster($registration, $event)) {
            throw ValidationException::withMessages([
                'registration' => 'This registration can no longer be edited — it may be past results-publish, or the event has closed.',
            ]);
        }

        // Editing in place is allowed even after payment is approved, but only if it doesn't
        // reduce what's owed — a decrease would need a refund/credit, which this path doesn't
        // handle (use cancel / cancel-with-refund for that instead). Snapshot the fee now so it
        // can be compared against the recalculated total after the roster change below.
        $feeService = app(FestSchoolEventFeeService::class);
        $feeRecord = $feeService->currentFeeRecordFor($event, $school->id);
        $dueBefore = (float) ($feeRecord?->total_due ?? 0);
        $isPaid = $feeRecord && in_array($feeRecord->status, ['paid', 'approved', 'verified'], true);

        if ($item->is_enabled === false) {
            throw ValidationException::withMessages(['registration' => 'This item is not open for registration.']);
        }
        app(FestItemRegistrationGate::class)->assertOpen($item);
        if ($event->schedule_published) {
            throw ValidationException::withMessages([
                'registration' => 'The squad cannot be changed once the fest-day schedule has been published.',
            ]);
        }

        // LIFE-10 fix (functional audit, 2026-08-11/12): captured before either branch below
        // mutates $registration in place, so this reflects the status the school actually
        // saw before submitting this edit — used after the transaction commits (never
        // inside it: a mid-transaction notification would still go out even if the fee-
        // decrease guard further down rolls the whole edit back) to tell both sides an
        // already-approved registration just lost that approval.
        $wasApproved = $registration->status === 'approved';

        if ($event->event_type === 'teacher_fest') {
            $updated = $this->updateTeacherRegistration($registration, $event, $item, $school, $performerIds, $feeService, $dueBefore);
            $this->notifyIfRosterEditRevokedApproval($wasApproved, $updated);

            return $updated;
        }

        $standbyIds = array_values(array_unique($standbyIds));
        $performerIds = array_values(array_diff(array_unique($performerIds), $standbyIds));

        if ($performerIds === []) {
            throw ValidationException::withMessages(['student_ids' => 'Select at least one participant.']);
        }

        $isGroup = FestTeamSquadRules::isMultiPerson($item->participant_type);
        if ($isGroup) {
            if (! filled($teamName)) {
                $teamName = $this->nextDefaultTeamName($event, $item, $school, $registration->id);
            }
            $error = $item->validateSquadCount(count($performerIds));
            if ($error) {
                throw ValidationException::withMessages(['student_ids' => $error]);
            }
        } else {
            $maxAllowed = (int) ($item->max_per_school ?? 1);
            if (count($performerIds) > $maxAllowed) {
                throw ValidationException::withMessages(['student_ids' => "Maximum {$maxAllowed} participant".($maxAllowed === 1 ? '' : 's').' allowed for this item.']);
            }
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

        $updated = DB::transaction(function () use ($registration, $event, $item, $school, $performerIds, $standbyIds, $teamName, $isGroup, $teamContacts, $feeService, $dueBefore, $isPaid) {
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
            if (FestTeamSquadRules::isMultiPerson($item->participant_type)) {
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
            // Sahodaya review rather than silently keeping the old approval. See
            // notifyIfRosterEditRevokedApproval() (LIFE-10) — the actual notification for
            // this fires after the transaction commits, not here.
            //
            // A 'rejected' registration goes through the same re-review logic — editing it
            // is how a school resubmits after a fix, not a silent no-op that would otherwise
            // leave the row permanently stuck on 'rejected' even after the roster changes.
            // See Documents/Path_breaks.md.
            if (in_array($registration->status, ['submitted', 'approved', 'rejected'], true)) {
                $wasRejected = $registration->status === 'rejected';
                $policy = app(FestParticipationPolicyService::class)->resolveForEvent($event);
                $feeService = app(FestSchoolEventFeeService::class);
                $feeRequiredBeforeApproval = ($policy['require_fee_before_approval'] ?? false) && $feeService->feeRequired($event);
                $feeUnpaid = $feeRequiredBeforeApproval && ! $feeService->isPaidForRegistration($event, $registration);

                $newStatus = ($item->head?->requiresManualApproval() || $event->requiresManualApproval() || $feeUnpaid)
                    ? 'submitted'
                    : 'approved';
                $registration->update(array_merge(
                    ['status' => $newStatus, 'submitted_at' => now()],
                    $wasRejected ? ['rejection_reason' => null, 'rejected_at' => null, 'rejected_by_user_id' => null] : [],
                ));
            }

            app(FestLevelRegistrationService::class)->syncRegistration($registration->fresh(['participants']));

            foreach ($registration->fresh(['participants'])->participants as $participant) {
                app(FestNumberingService::class)->assignParticipantNumbers($participant);
            }

            $feeAfter = $feeService->recalculate($event, $school->id);

            // A decrease needs a refund/credit when paid, which this in-place edit path doesn't create —
            // send the school to cancel (or cancel-with-refund, once paid) instead. Throwing
            // here rolls back the whole roster change, including the recalculate() above.
            if ($isPaid && round((float) $feeAfter->total_due, 2) < round($dueBefore, 2)) {
                throw ValidationException::withMessages([
                    'registration' => 'This change would reduce the fee owed — cancel this item instead so the difference can be credited, rather than editing it in place.',
                ]);
            }

            return $registration->fresh(['participants.student', 'item']);
        });

        $this->notifyIfRosterEditRevokedApproval($wasApproved, $updated);

        return $updated;
    }

    /**
     * Shared tail for updateForSchool()'s two branches — see the LIFE-10 comment on
     * $wasApproved above for why this runs after the transaction, not inside it.
     */
    private function notifyIfRosterEditRevokedApproval(bool $wasApproved, FestRegistration $updated): void
    {
        if ($wasApproved && $updated->status === 'submitted') {
            $notifier = app(FestEventNotifier::class);
            $notifier->registrationNeedsReapproval($updated);
            $notifier->registrationNeedsReapprovalAdmin($updated);
        }
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
        FestSchoolEventFeeService $feeService,
        float $dueBefore,
    ): FestRegistration {
        $teacherIds = array_values(array_unique($teacherIds));
        if ($teacherIds === []) {
            throw ValidationException::withMessages(['teacher_ids' => 'Select at least one teacher.']);
        }

        if (count($teacherIds) > 1 && ! FestTeamSquadRules::isMultiPerson($item->participant_type)) {
            throw ValidationException::withMessages(['teacher_ids' => 'This item allows only one teacher.']);
        }

        return DB::transaction(function () use ($registration, $event, $school, $teacherIds, $feeService, $dueBefore) {
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
            } elseif ($registration->status === 'rejected') {
                // Same resubmit-on-edit handling as the student path above.
                $registration->update([
                    'status' => 'submitted', 'submitted_at' => now(),
                    'rejection_reason' => null, 'rejected_at' => null, 'rejected_by_user_id' => null,
                ]);
            }

            app(FestLevelRegistrationService::class)->syncRegistration($registration->fresh(['participants']));
            $feeAfter = $feeService->recalculate($event, $school->id);

            if (round((float) $feeAfter->total_due, 2) < round($dueBefore, 2)) {
                throw ValidationException::withMessages([
                    'registration' => 'This change would reduce the fee owed — cancel this item instead so the difference can be credited, rather than editing it in place.',
                ]);
            }

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

        if (count($teacherIds) > 1 && ! FestTeamSquadRules::isMultiPerson($item->participant_type)) {
            throw ValidationException::withMessages(['teacher_ids' => 'This item allows only one teacher.']);
        }

        try {
            return DB::transaction(function () use ($event, $item, $school, $teacherIds) {
                $registration = FestRegistration::create([
                    'event_id'     => $event->id,
                    'item_id'      => $item->id,
                    'school_id'    => $school->id,
                    'status'       => 'approved',
                    'submitted_at' => now(),
                ]);


                if ($event->rootEvent()->usesPhasedRegionalBilling() && $item->phase) {
                    app(FestSchoolPhaseRegionService::class)
                        ->lockForRegistration($event, $item->phase, $school->id);
                }

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
                foreach ($registration->fresh(['participants'])->participants as $participant) {
                    app(FestNumberingService::class)->assignParticipantNumbers($participant);
                }
                app(FestSchoolEventFeeService::class)->recalculate($event, $school->id);

                return $registration->load(['participants.teacher', 'item']);
            });
        } catch (QueryException $e) {
            $this->abortOnFestRegistrationDuplicate($e);

            throw $e;
        }
    }
}
