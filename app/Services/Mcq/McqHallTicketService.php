<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;
use Illuminate\Support\Facades\DB;

class McqHallTicketService
{
    /**
     * Assign the next registration number (stored as hall_ticket_no), scoped to the
     * registration's class bucket (student's class name, or "Teacher"/"Unassigned").
     * Numbers are unique WITHIN a class exam-wide, but different classes intentionally
     * reuse the same numbers (e.g. every class has its own "Roll 1") — this is a class
     * roll number, not an exam-wide unique ticket. Every bucket starts counting from the
     * exam's next_hall_ticket_no setting, which is a fixed starting digit here, not a
     * moving cursor — it is never incremented, only compared for equality when the admin
     * edits it (see McqExamController::update()'s "already issued" guard).
     */
    public function issueForRegistration(McqRegistration $registration, bool $autoApprove = false): McqRegistration
    {
        if ($registration->hall_ticket_no) {
            return $registration;
        }

        if ($registration->approval_status !== 'approved') {
            if ($autoApprove) {
                $registration->update([
                    'approval_status'     => 'approved',
                    'approved_at'         => now(),
                    'approved_by_user_id' => auth()->id(),
                ]);
            } else {
                throw new \InvalidArgumentException('Hall tickets are issued only after Sahodaya approves the registration.');
            }
        }

        return DB::transaction(function () use ($registration) {
            $exam = McqExam::where('id', $registration->exam_id)->lockForUpdate()->firstOrFail();
            $registration->loadMissing(['student.schoolClass', 'teacher']);

            $ticketNo = (string) $this->nextNumberForClass($exam, $registration);

            $registration->update([
                'hall_ticket_no' => $ticketNo,
                'hall_room'      => $registration->hall_room,
            ]);

            // Every hall ticket carries an online exam passcode (printed only for online exams).
            app(McqExamPasscodeService::class)->ensure($registration);

            if ($exam->allowsVenueKeycodeJoin()) {
                app(McqVenueKeycodeService::class)->ensure($registration->fresh());
            }

            return $registration->fresh();
        });
    }

    public function issueBulk(McqExam $exam, bool $autoApprove = true): int
    {
        $count = 0;

        $query = McqRegistration::where('exam_id', $exam->id)
            ->where('status', '!=', 'cancelled')
            ->whereNull('hall_ticket_no');

        if (! $autoApprove) {
            $query->where('approval_status', 'approved');
        }

        $unassigned = $query->with(['school:id,name', 'student:id,name,school_class_id', 'student.schoolClass:id,name', 'teacher:id,name'])
            ->get()
            ->sort(fn ($a, $b) => self::sortKey($a) <=> self::sortKey($b));

        foreach ($unassigned as $registration) {
            $this->issueForRegistration($registration, $autoApprove);
            $count++;
        }

        if ($count > 0) {
            app(McqSeatingService::class)->recalculate($exam);
        }

        return $count;
    }

    /**
     * Renumber every active registration into per-class roll numbers:
     * one contiguous 1..N sequence per class bucket (school name, then participant name),
     * starting from the exam's next_hall_ticket_no setting. Cancelled registrations lose their number.
     *
     * @return array{renumbered: int, cleared: int, start: int, classes: int}
     */
    public function renumberByClass(McqExam $exam, bool $includeUnassigned = true): array
    {
        return DB::transaction(function () use ($exam, $includeUnassigned) {
            $exam = McqExam::where('id', $exam->id)->lockForUpdate()->firstOrFail();

            $query = McqRegistration::where('exam_id', $exam->id);
            if (! $includeUnassigned) {
                $query->whereNotNull('hall_ticket_no');
            }

            $all = $query->get(['id', 'hall_ticket_no', 'status', 'approval_status']);

            if ($all->isEmpty()) {
                return ['renumbered' => 0, 'cleared' => 0, 'start' => (int) $exam->next_hall_ticket_no, 'classes' => 0];
            }

            $startNumber = (int) $exam->next_hall_ticket_no;
            $cancelledIds = $all->where('status', 'cancelled')->pluck('id');
            $activeIds = $all->where('status', '!=', 'cancelled')->pluck('id');

            // Auto-approve any active registrations being given a roll number
            McqRegistration::whereIn('id', $activeIds)
                ->where('approval_status', '!=', 'approved')
                ->update([
                    'approval_status'     => 'approved',
                    'approved_at'         => now(),
                    'approved_by_user_id' => auth()->id(),
                ]);

            $active = McqRegistration::whereIn('id', $activeIds)
                ->with(['school:id,name', 'student:id,name,school_class_id', 'student.schoolClass:id,name', 'teacher:id,name'])
                ->get();

            $groups = $active->groupBy(fn ($r) => self::classBucketKey($r));

            $renumbered = 0;
            foreach ($groups as $group) {
                $sorted = $group->sort(function ($a, $b) {
                    $schoolCmp = strcasecmp($a->school?->name ?? '', $b->school?->name ?? '');
                    if ($schoolCmp !== 0) {
                        return $schoolCmp;
                    }

                    $nameA = $a->student?->name ?? $a->teacher?->name ?? '';
                    $nameB = $b->student?->name ?? $b->teacher?->name ?? '';

                    return strcasecmp($nameA, $nameB);
                })->values();

                $number = (int) ($exam->settings_json['hall_ticket_class_starts'][self::classBucketKey($sorted->first())] ?? $startNumber);
                foreach ($sorted as $registration) {
                    DB::table('mcq_registrations')->where('id', $registration->id)->update([
                        'hall_ticket_no' => (string) $number,
                    ]);
                    $number++;
                    $renumbered++;
                }
            }

            if ($cancelledIds->isNotEmpty()) {
                DB::table('mcq_registrations')->whereIn('id', $cancelledIds)->update(['hall_ticket_no' => null]);
            }

            // Ensure passcodes and recalculate seating
            app(McqExamPasscodeService::class)->ensureForExam($exam);
            app(McqSeatingService::class)->recalculate($exam);

            return [
                'renumbered' => $renumbered,
                'cleared'    => $cancelledIds->count(),
                'start'      => $startNumber,
                'classes'    => $groups->count(),
            ];
        });
    }

    /** Next free number within this registration's class bucket, exam-wide (not scoped to a school). */
    private function nextNumberForClass(McqExam $exam, McqRegistration $registration): int
    {
        $classKey = self::classBucketKey($registration);

        $query = McqRegistration::where('exam_id', $exam->id)->whereNotNull('hall_ticket_no');

        if ($classKey === 'Teacher') {
            $query->whereNotNull('teacher_id')->whereNull('student_id');
        } elseif ($classKey === 'Unassigned') {
            $query->whereHas('student', fn ($q) => $q->whereNull('school_class_id'));
        } else {
            $query->whereHas('student.schoolClass', fn ($q) => $q->where('name', $classKey));
        }

        $max = $query->pluck('hall_ticket_no')->map(fn ($v) => (int) $v)->max();

        return max(
            (int) ($exam->settings_json['hall_ticket_class_starts'][$classKey] ?? $exam->next_hall_ticket_no),
            $max !== null ? $max + 1 : 0,
        );
    }

    /** Bucket key a registration's roll number is scoped to: its class name, "Teacher", or "Unassigned". */
    private static function classBucketKey(McqRegistration $registration): string
    {
        if ($registration->isTeacherRegistration()) {
            return 'Teacher';
        }

        return $registration->student?->schoolClass?->name ?: 'Unassigned';
    }

    /** Class (numeric-aware) first so issueBulk() fills each class bucket in a stable order, then school, then name. */
    private static function sortKey(McqRegistration $registration): string
    {
        $classKey = self::classBucketKey($registration);
        $classSortPrefix = in_array($classKey, ['Teacher', 'Unassigned'], true)
            ? '9999zzz'
            : str_pad((string) ((int) preg_replace('/\D/', '', $classKey) ?: 999), 4, '0', STR_PAD_LEFT);
        $schoolName = mb_strtolower($registration->school?->name ?? '');
        $name = mb_strtolower($registration->student?->name ?? $registration->teacher?->name ?? '');

        return $classSortPrefix.mb_strtolower($classKey)."\0".$schoolName."\0".$name;
    }
}
