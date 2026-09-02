<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;
use Illuminate\Support\Facades\DB;

class McqHallTicketService
{
    /**
     * Assign the next exam registration number (stored as hall_ticket_no).
     * Numbering starts at the exam's next_hall_ticket_no setting (any positive integer).
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
            $ticketNo = (string) $exam->next_hall_ticket_no;

            $exam->update(['next_hall_ticket_no' => $exam->next_hall_ticket_no + 1]);

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
     * Renumber every already-issued, non-cancelled registration into contiguous
     * per-school blocks (school name, then class, then participant name), keeping
     * hall_ticket_no exam-wide unique — only the ORDER of the existing numbers changes,
     * starting again from whatever digit this exam originally started at. Cancelled
     * registrations lose their number entirely so it can't collide with a reassigned one.
     *
     * @return array{renumbered: int, cleared: int, start: int}
     */
    public function renumberBySchool(McqExam $exam): array
    {
        return DB::transaction(function () use ($exam) {
            $exam = McqExam::where('id', $exam->id)->lockForUpdate()->firstOrFail();

            $issued = McqRegistration::where('exam_id', $exam->id)
                ->whereNotNull('hall_ticket_no')
                ->get(['id', 'hall_ticket_no', 'status']);

            if ($issued->isEmpty()) {
                return ['renumbered' => 0, 'cleared' => 0, 'start' => (int) $exam->next_hall_ticket_no];
            }

            $startNumber = (int) $issued->min(fn ($r) => (int) $r->hall_ticket_no);
            $cancelledIds = $issued->where('status', 'cancelled')->pluck('id');
            $activeIds = $issued->where('status', '!=', 'cancelled')->pluck('id');

            $sorted = McqRegistration::whereIn('id', $activeIds)
                ->with(['school:id,name', 'student:id,name,school_class_id', 'student.schoolClass:id,name', 'teacher:id,name'])
                ->get()
                ->sort(fn ($a, $b) => self::sortKey($a) <=> self::sortKey($b))
                ->values();

            $number = $startNumber;
            foreach ($sorted as $registration) {
                DB::table('mcq_registrations')->where('id', $registration->id)->update(['hall_ticket_no' => (string) $number]);
                $number++;
            }

            if ($cancelledIds->isNotEmpty()) {
                DB::table('mcq_registrations')->whereIn('id', $cancelledIds)->update(['hall_ticket_no' => null]);
            }

            $exam->update(['next_hall_ticket_no' => $number]);

            return [
                'renumbered' => $sorted->count(),
                'cleared'    => $cancelledIds->count(),
                'start'      => $startNumber,
            ];
        });
    }

    /** School name, then class (numeric-aware), then participant name — teachers/no-class sort after numbered classes. */
    private static function sortKey(McqRegistration $registration): string
    {
        $schoolName = mb_strtolower($registration->school?->name ?? '');
        $className = $registration->student?->schoolClass?->name;
        $classKey = $className ? str_pad((string) ((int) preg_replace('/\D/', '', $className) ?: 999), 4, '0', STR_PAD_LEFT).mb_strtolower($className) : '9999zzz';
        $name = mb_strtolower($registration->student?->name ?? $registration->teacher?->name ?? '');

        return $schoolName."\0".$classKey."\0".$name;
    }
}
