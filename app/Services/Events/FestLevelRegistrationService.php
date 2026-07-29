<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestLevelRegistration;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\Student;
use App\Models\Teacher;

class FestLevelRegistrationService
{
    public function issueForStudent(FestEvent $event, Student $student, ?string $schoolId = null): string
    {
        // Look up ANY existing row for this event+student, not just an 'active' one — the
        // table has a unique(event_id, student_id) constraint, so if a student was previously
        // deactivated (see deactivateIfNoActiveItems() below, after cancelling every item they
        // had) and is now being added to a new item, we must reactivate that same row rather
        // than creating a second one, which would violate the unique index.
        $existing = FestLevelRegistration::where('event_id', $event->id)
            ->where('student_id', $student->id)
            ->first();

        if ($existing) {
            if ($existing->status !== 'active') {
                $existing->update(['status' => 'active', 'registered_at' => now()]);
            }

            return $existing->registration_number;
        }

        $number = app(FestNumberingService::class)->nextEventRegNumber($event);
        $resolvedSchoolId = $schoolId ?? $student->tenant_id;

        FestLevelRegistration::create([
            'event_id'             => $event->id,
            'student_id'           => $student->id,
            'school_id'            => $resolvedSchoolId,
            'registration_number'  => $number,
            'status'               => 'active',
            'registered_at'        => now(),
        ]);

        return $number;
    }

    /**
     * After a registration is cancelled/withdrawn/rejected, check whether this student still
     * has any other billable item registration anywhere in this event (scoped the same way
     * FestSportsCompositeFeeService counts them — across partition children too, via
     * reportableEventIds(), and excluding standbys, who are never billed). If none remain,
     * flip this student's FestLevelRegistration off 'active'.
     *
     * Without this, a student's event-level registration (created once, the first time they
     * were added to any item — see issueForStudent() above) never gets touched by
     * cancellation: FestRegistrationService::cancel()/cancelWithRefund() and
     * FestRegistrationBulkService::rejectMany() only ever flip the item-level FestRegistration
     * status. Since the composite fee model (FestSportsCompositeFeeService::calculate())
     * counts billable students primarily from FestLevelRegistration rows with status='active',
     * a student who cancels every single item they had would still be billed the per-student
     * registration fee forever, with zero items left to show for it.
     */
    public function deactivateIfNoActiveItems(FestEvent $event, string $studentId): void
    {
        $stillActive = FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->whereIn('event_id', $event->reportableEventIds())
                ->whereIn('status', ['submitted', 'approved']))
            ->where('participant_role', '!=', 'standby')
            ->where('student_id', $studentId)
            ->exists();

        if ($stillActive) {
            return;
        }

        FestLevelRegistration::where('event_id', $event->id)
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->update(['status' => 'withdrawn']);
    }

    public function syncParticipant(FestParticipant $participant): void
    {
        $participant->loadMissing('student', 'registration.event');
        $student = $participant->student;
        $event = $participant->registration?->event;

        if (! $student || ! $event) {
            return;
        }

        $schoolId = $participant->registration?->school_id;
        $number = $this->issueForStudent($event, $student, $schoolId);

        $participant->update(['level_registration_number' => $number]);
    }

    /** @return int Number backfilled */
    public function backfillEvent(FestEvent $event): int
    {
        $count = 0;

        FestParticipant::whereHas('registration', fn ($q) => $q->where('event_id', $event->id))
            ->whereNotNull('student_id')
            ->with(['student', 'registration.event'])
            ->each(function (FestParticipant $p) use (&$count) {
                if ($p->level_registration_number) {
                    return;
                }
                $this->syncParticipant($p);
                $count++;
            });

        return $count;
    }

    public function issueForTeacher(FestEvent $event, Teacher $teacher): string
    {
        $existing = FestParticipant::whereHas('registration', fn ($q) => $q->where('event_id', $event->id))
            ->where('teacher_id', $teacher->id)
            ->whereNotNull('level_registration_number')
            ->value('level_registration_number');

        if ($existing) {
            return $existing;
        }

        return app(FestNumberingService::class)->nextEventRegNumber($event);
    }

    public function syncTeacherParticipant(FestParticipant $participant): void
    {
        $teacher = $participant->teacher;
        $event = $participant->registration?->event;

        if (! $teacher || ! $event || $participant->level_registration_number) {
            return;
        }

        $participant->update([
            'level_registration_number' => $this->issueForTeacher($event, $teacher),
        ]);
    }

    public function syncRegistration(FestRegistration $registration): void
    {
        $registration->loadMissing('participants.student', 'participants.teacher', 'event');
        foreach ($registration->participants as $participant) {
            if ($participant->student_id) {
                $this->syncParticipant($participant);
            } elseif ($participant->teacher_id) {
                $this->syncTeacherParticipant($participant);
            }
        }
    }
}
