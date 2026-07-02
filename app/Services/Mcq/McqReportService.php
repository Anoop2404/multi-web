<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Support\ExcelExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class McqReportService
{
    /** @return list<array<string, mixed>> */
    public function registrationRows(McqExam $exam, ?string $schoolId = null): array
    {
        $query = McqRegistration::where('exam_id', $exam->id)
            ->with(['student.schoolClass', 'school', 'mark', 'feeReceipt']);

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        return $query->orderBy('hall_ticket_no')
            ->orderBy('id')
            ->get()
            ->map(fn (McqRegistration $reg) => [
                'hall_ticket_no'   => $reg->hall_ticket_no,
                'student_name'     => $reg->student?->name,
                'reg_no'           => $reg->student?->reg_no,
                'class_name'       => $reg->student?->schoolClass?->name,
                'school_name'      => $reg->school?->name,
                'approval_status'  => $reg->approval_status,
                'attendance_status'=> $reg->attendance_status,
                'score'            => $reg->mark?->score,
                'rank'             => $reg->mark?->rank,
                'grade'            => $reg->mark?->grade,
                'fee_status'       => $reg->feeReceipt?->status,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function feeSummaryRows(McqExam $exam): array
    {
        return McqSchoolFee::where('exam_id', $exam->id)
            ->with(['school', 'feeReceipt'])
            ->orderBy('school_id')
            ->get()
            ->map(fn (McqSchoolFee $fee) => [
                'school_name'    => $fee->school?->name,
                'student_count'  => $fee->student_count,
                'total_due'      => (float) $fee->total_due,
                'status'         => $fee->status,
                'receipt_status' => $fee->feeReceipt?->status,
                'payment_date'   => $fee->feeReceipt?->payment_date?->format('Y-m-d'),
                'transaction_ref'=> $fee->feeReceipt?->transaction_ref,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function schoolToppers(McqExam $exam, string $schoolId, int $limit = 25): array
    {
        if (! $exam->results_published) {
            return [];
        }

        return McqRegistration::where('exam_id', $exam->id)
            ->where('school_id', $schoolId)
            ->whereHas('mark')
            ->with(['student.schoolClass', 'mark'])
            ->get()
            ->sortBy(fn (McqRegistration $r) => $r->mark?->rank ?? 9999)
            ->take($limit)
            ->values()
            ->map(fn (McqRegistration $reg) => [
                'rank'       => $reg->mark?->rank,
                'name'       => $reg->student?->name,
                'reg_no'     => $reg->student?->reg_no,
                'class_name' => $reg->student?->schoolClass?->name,
                'score'      => $reg->mark?->score,
                'grade'      => $reg->mark?->grade,
            ])
            ->all();
    }

    public function exportRegistrationRegister(McqExam $exam, ?string $schoolId = null): StreamedResponse
    {
        $rows = $this->registrationRows($exam, $schoolId);
        $suffix = $schoolId ? '-school-'.substr($schoolId, 0, 8) : '';

        return ExcelExport::download(
            'mcq-registration-register-'.$exam->id.$suffix,
            ['Hall ticket', 'Student', 'Reg. no', 'Class', 'School', 'Approval', 'Attendance', 'Score', 'Rank', 'Grade', 'Fee'],
            collect($rows)->map(fn ($r) => [
                $r['hall_ticket_no'],
                $r['student_name'],
                $r['reg_no'],
                $r['class_name'],
                $r['school_name'],
                $r['approval_status'],
                $r['attendance_status'],
                $r['score'],
                $r['rank'],
                $r['grade'],
                $r['fee_status'],
            ]),
        );
    }

    public function exportFeeSummary(McqExam $exam): StreamedResponse
    {
        $rows = $this->feeSummaryRows($exam);

        return ExcelExport::download(
            'mcq-fee-summary-'.$exam->id,
            ['School', 'Students', 'Amount due', 'Status', 'Receipt status', 'Payment date', 'Transaction ref'],
            collect($rows)->map(fn ($r) => [
                $r['school_name'],
                $r['student_count'],
                $r['total_due'],
                $r['status'],
                $r['receipt_status'],
                $r['payment_date'],
                $r['transaction_ref'],
            ]),
        );
    }

    public function exportAttendance(McqExam $exam, ?string $schoolId = null): StreamedResponse
    {
        $rows = $this->registrationRows($exam, $schoolId);
        $suffix = $schoolId ? '-school-'.substr($schoolId, 0, 8) : '';

        return ExcelExport::download(
            'mcq-attendance-'.$exam->id.$suffix,
            ['Hall ticket', 'Student', 'Reg. no', 'Class', 'School', 'Attendance'],
            collect($rows)->map(fn ($r) => [
                $r['hall_ticket_no'],
                $r['student_name'],
                $r['reg_no'],
                $r['class_name'],
                $r['school_name'],
                $r['attendance_status'] ?? 'pending',
            ]),
        );
    }
}
