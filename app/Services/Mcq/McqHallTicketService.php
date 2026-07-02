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
            ->orderBy('id')
            ->each(function (McqRegistration $registration) use (&$count) {
                $this->issueForRegistration($registration);
                $count++;
            });

        return $count;
    }
}
