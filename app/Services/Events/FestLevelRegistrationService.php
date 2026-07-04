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
    public function issueForStudent(FestEvent $event, Student $student): string
    {
        $existing = FestLevelRegistration::where('event_id', $event->id)
            ->where('student_id', $student->id)
            ->value('registration_number');

        if ($existing) {
            return $existing;
        }

        $prefix = strtoupper(substr($event->level_round ?? 'S', 0, 1));
        $number = app(FestNumberingService::class)->nextEventRegNumber($event);

        FestLevelRegistration::create([
            'event_id'             => $event->id,
            'student_id'           => $student->id,
            'school_id'            => $student->tenant_id,
            'registration_number'  => $number,
            'status'               => 'active',
            'registered_at'        => now(),
        ]);

        return $number;
    }

    public function syncParticipant(FestParticipant $participant): void
    {
        $student = $participant->student;
        $event = $participant->registration?->event;

        if (! $student || ! $event) {
            return;
        }

        $number = $this->issueForStudent($event, $student);

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

        $prefix = strtoupper(substr($event->level_round ?? 'S', 0, 1));
        $seq = FestParticipant::whereHas('registration', fn ($q) => $q->where('event_id', $event->id))
            ->whereNotNull('teacher_id')
            ->count() + 1;

        return sprintf('%s-T%04d', $prefix, $seq);
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
        $registration->loadMissing('participants.student', 'participants.teacher');
        foreach ($registration->participants as $participant) {
            if ($participant->student_id) {
                $this->syncParticipant($participant);
            } elseif ($participant->teacher_id) {
                $this->syncTeacherParticipant($participant);
            }
        }
    }
}
