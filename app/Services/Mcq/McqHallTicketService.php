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
    public function issueForRegistration(McqRegistration $registration): McqRegistration
    {
        if ($registration->hall_ticket_no) {
            return $registration;
        }

        if ($registration->approval_status !== 'approved') {
            throw new \InvalidArgumentException('Hall tickets are issued only after Sahodaya approves the registration.');
        }

        return DB::transaction(function () use ($registration) {
            $exam = McqExam::where('id', $registration->exam_id)->lockForUpdate()->firstOrFail();
            $registration->loadMissing(['student.schoolClass', 'teacher']);

            $ticketNo = (string) $this->nextNumberForClass($exam, $registration);

            $registration->update([
                'hall_ticket_no' => $ticketNo,
                'hall_room'      => $registration->hall_room,
            ]);

            return $registration->fresh();
        });
    }

    public function issueBulk(McqExam $exam): int
    {
        $count = 0;

        McqRegistration::where('exam_id', $exam->id)
            ->where('approval_status', 'approved')
            ->whereNull('hall_ticket_no')
            ->with(['school:id,name', 'student:id,name,school_class_id', 'student.schoolClass:id,name', 'teacher:id,name'])
            ->get()
            ->sort(fn ($a, $b) => self::sortKey($a) <=> self::sortKey($b))
            ->each(function (McqRegistration $registration) use (&$count) {
                $this->issueForRegistration($registration);
                $count++;
            });

        return $count;
    }

    /**
     * Renumber every already-issued, non-cancelled registration into per-class roll
     * numbers: one contiguous 1..N sequence per class bucket (school name, then
     * participant name, within the bucket), each bucket restarting from the same
     * starting digit this exam originally used. Cancelled registrations lose their
     * number entirely.
     *
     * @return array{renumbered: int, cleared: int, start: int, classes: int}
     */
    public function renumberByClass(McqExam $exam): array
    {
        return DB::transaction(function () use ($exam) {
            $exam = McqExam::where('id', $exam->id)->lockForUpdate()->firstOrFail();

            $issued = McqRegistration::where('exam_id', $exam->id)
                ->whereNotNull('hall_ticket_no')
                ->get(['id', 'hall_ticket_no', 'status']);

            if ($issued->isEmpty()) {
                return ['renumbered' => 0, 'cleared' => 0, 'start' => (int) $exam->next_hall_ticket_no, 'classes' => 0];
            }

            $startNumber = (int) $issued->min(fn ($r) => (int) $r->hall_ticket_no);
            $cancelledIds = $issued->where('status', 'cancelled')->pluck('id');
            $activeIds = $issued->where('status', '!=', 'cancelled')->pluck('id');

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

                $number = $startNumber;
                foreach ($sorted as $registration) {
                    DB::table('mcq_registrations')->where('id', $registration->id)->update(['hall_ticket_no' => (string) $number]);
                    $number++;
                    $renumbered++;
                }
            }

            if ($cancelledIds->isNotEmpty()) {
                DB::table('mcq_registrations')->whereIn('id', $cancelledIds)->update(['hall_ticket_no' => null]);
            }

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

        return $max !== null ? $max + 1 : (int) $exam->next_hall_ticket_no;
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
