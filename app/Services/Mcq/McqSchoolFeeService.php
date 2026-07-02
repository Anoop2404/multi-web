<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Models\Tenant;
use App\Services\Fees\ProgramFeeReceiptMailer;
use App\Services\Fees\ProgramFeeReceiptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class McqSchoolFeeService
{
    public function syncForSchool(McqExam $exam, Tenant $school): McqSchoolFee
    {
        $count = McqRegistration::where('exam_id', $exam->id)
            ->where('school_id', $school->id)
            ->count();

        $totalDue = $exam->hasFee() ? round($count * (float) $exam->fee_amount, 2) : 0;

        return McqSchoolFee::updateOrCreate(
            ['exam_id' => $exam->id, 'school_id' => $school->id],
            [
                'student_count' => $count,
                'total_due'     => $totalDue,
                'status'        => $totalDue > 0 ? 'pending' : 'waived',
            ]
        );
    }

    public function markRegistrationsPaid(McqSchoolFee $schoolFee): void
    {
        // Fee verification is handled by McqRegistrationApprovalService::approveSchoolBatch().
    }

    public function approve(McqSchoolFee $schoolFee, int $userId): int
    {
        $receipt = $schoolFee->feeReceipt;
        abort_unless($receipt && $receipt->status === 'uploaded', 422, 'No uploaded proof to approve.');

        return DB::transaction(function () use ($schoolFee, $receipt, $userId) {
            $receipt->update([
                'status'      => 'approved',
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ]);

            $schoolFee->update(['status' => 'approved']);

            $issued = app(ProgramFeeReceiptService::class)->issueMcqSchoolBatch(
                $schoolFee->fresh(['exam', 'school']),
                $receipt->fresh(),
            );

            $count = app(McqRegistrationApprovalService::class)->approveSchoolBatch($schoolFee->fresh(), $userId);

            app(ProgramFeeReceiptMailer::class)->sendApproved(
                $schoolFee->school,
                $issued,
                'MCQ exam fee',
                $schoolFee->exam?->title ?? 'MCQ Exam',
                adminPath: 'payments',
            );

            return $count;
        });
    }
}
