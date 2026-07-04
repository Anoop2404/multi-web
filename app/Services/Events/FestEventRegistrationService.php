<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestLevelRegistration;
use App\Models\FestRegistration;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

class FestEventRegistrationService
{
    public function requireEventRegistration(FestEvent $event): bool
    {
        return (bool) ($event->require_event_registration ?? false);
    }

    public function isEventRegistrationOpen(FestEvent $event): bool
    {
        if (! $event->isRegistrationOpen()) {
            return false;
        }

        $today = now()->startOfDay();

        if ($event->event_reg_start && $today->lt(Carbon::parse($event->event_reg_start)->startOfDay())) {
            return false;
        }

        if ($event->event_reg_end && $today->gt(Carbon::parse($event->event_reg_end)->startOfDay())) {
            return false;
        }

        return true;
    }

    public function studentIsRegistered(FestEvent $event, int $studentId): bool
    {
        return FestLevelRegistration::where('event_id', $event->id)
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->exists();
    }

    /** @return list<int> */
    public function registeredStudentIds(FestEvent $event, string $schoolId): array
    {
        return FestLevelRegistration::where('event_id', $event->id)
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->pluck('student_id')
            ->all();
    }

    public function registerStudent(FestEvent $event, Student $student, Tenant $school): FestLevelRegistration
    {
        abort_if($student->tenant_id !== $school->id, 403);
        abort_if($school->parent_id !== $event->tenant_id, 403);
        $this->assertSchoolMembershipApproved($school);
        abort_if(! $this->isEventRegistrationOpen($event), 422, 'Event registration is closed.');

        $existing = FestLevelRegistration::where('event_id', $event->id)
            ->where('student_id', $student->id)
            ->first();

        if ($existing) {
            if ($existing->status !== 'active') {
                $existing->update(['status' => 'active', 'school_id' => $school->id, 'registered_at' => now()]);
            }

            return $existing;
        }

        $number = app(FestNumberingService::class)->nextEventRegNumber($event);

        $record = FestLevelRegistration::create([
            'event_id'             => $event->id,
            'student_id'           => $student->id,
            'school_id'            => $school->id,
            'registration_number'  => $number,
            'status'               => 'active',
            'registered_at'        => now(),
        ]);

        app(FestSchoolEventFeeService::class)->recalculate($event, $school->id);

        return $record;
    }

    /** @param  list<int>  $studentIds */
    public function registerStudents(FestEvent $event, Tenant $school, array $studentIds): int
    {
        $count = 0;
        foreach (array_unique($studentIds) as $studentId) {
            $student = Student::where('id', $studentId)->where('tenant_id', $school->id)->first();
            if (! $student) {
                continue;
            }
            $this->registerStudent($event, $student, $school);
            $count++;
        }

        return $count;
    }

    public function assertStudentEligible(FestEvent $event, int $studentId): void
    {
        if (! $this->requireEventRegistration($event)) {
            return;
        }

        abort_if(
            ! $this->studentIsRegistered($event, $studentId),
            422,
            'Student must be registered for the event before item registration.'
        );
    }

    /** @return list<array<string, mixed>> */
    public function studentEventRegistrations(FestEvent $event, string $schoolId): array
    {
        return FestLevelRegistration::where('event_id', $event->id)
            ->where('school_id', $schoolId)
            ->with('student:id,name,reg_no')
            ->orderBy('registration_number')
            ->get()
            ->map(fn (FestLevelRegistration $r) => [
                'id' => $r->id,
                'student_id' => $r->student_id,
                'student_name' => $r->student?->name,
                'reg_no' => $r->student?->reg_no,
                'registration_number' => $r->registration_number,
                'registered_at' => $r->registered_at?->toIso8601String(),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function studentItemRegistrations(FestEvent $event, int $studentId): array
    {
        return FestRegistration::where('event_id', $event->id)
            ->whereIn('status', ['submitted', 'approved'])
            ->whereHas('participants', fn ($q) => $q->where('student_id', $studentId))
            ->with(['item:id,title,head_id', 'participants' => fn ($q) => $q->where('student_id', $studentId)])
            ->get()
            ->map(fn (FestRegistration $reg) => [
                'id' => $reg->id,
                'item_id' => $reg->item_id,
                'item_title' => $reg->item?->title,
                'status' => $reg->status,
                'chest_no' => $reg->participants->first()?->chest_no,
                'item_registration_number' => $reg->participants->first()?->item_registration_number,
                'level_registration_number' => $reg->participants->first()?->level_registration_number,
            ])
            ->values()
            ->all();
    }

    public function assertSchoolMembershipApproved(Tenant $school): void
    {
        abort_if(
            $school->membership_status !== 'approved',
            422,
            'Your school\'s Sahodaya membership must be approved before fest registration.',
        );
    }
}
