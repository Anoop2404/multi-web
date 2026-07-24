<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Models\ProgramFeeCredit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class McqExamStatusService
{
    public function __construct(
        private McqExamNotifier $notifier,
        private \App\Services\Audit\PlatformAuditLogger $audit
    ) {}

    public function transitionToCancelled(McqExam $exam, bool $confirmCreditAll = false): void
    {
        $paidFees = McqSchoolFee::where('exam_id', $exam->id)
            ->where('amount_paid', '>', 0)
            ->get();

        if ($paidFees->isNotEmpty() && !$confirmCreditAll) {
            $count = $paidFees->count();
            $total = $paidFees->sum('amount_paid');

            throw ValidationException::withMessages([
                'status' => "This exam has {$count} school(s) with approved payments totaling ₹{$total}. To proceed with cancellation and issue credits, you must confirm 'Credit all paid fees'.",
            ]);
        }

        DB::transaction(function () use ($exam, $paidFees) {
            $registrations = McqRegistration::where('exam_id', $exam->id)
                ->whereIn('status', ['registered', 'submitted', 'approved'])
                ->get();

            if ($registrations->isNotEmpty()) {
                McqRegistration::whereIn('id', $registrations->pluck('id'))->update([
                    'status' => 'cancelled',
                    'hall_ticket_no' => null,
                ]);

                // Cleanup orphan data like marks and certificates for MCQ
                \App\Models\McqMark::whereIn('registration_id', $registrations->pluck('id'))->delete();
                \App\Models\McqCertificate::whereIn('registration_id', $registrations->pluck('id'))->delete();
            }

            $issuedCredits = collect();
            
            foreach ($paidFees as $fee) {
                $feeAfter = app(McqSchoolFeeService::class)->recalculate($exam, $fee->school_id);
                $reduction = round((float)$fee->total_due - (float)$feeAfter->total_due, 2);
                $paidBefore = (float)$fee->amount_paid;
                
                $creditAmount = min($reduction, $paidBefore);
                
                if ($creditAmount > 0) {
                    $credit = ProgramFeeCredit::create([
                        'creditable_type' => McqSchoolFee::class,
                        'creditable_id'   => $feeAfter->id,
                        'source_type'     => McqExam::class,
                        'source_id'       => $exam->id,
                        'amount'          => $creditAmount,
                        'reason'          => 'Exam cancelled after payment',
                        'created_by_user_id' => auth()->id(),
                    ]);
                    $issuedCredits->push($credit);
                }
            }

            $exam->update(['status' => 'cancelled']);

            $this->notifier->examCancelled($exam, $issuedCredits);

            $this->audit->mcqExam(
                $exam,
                'overview',
                'mcq.exam.cancelled',
                "Exam cancelled: {$exam->title}",
                ['status' => 'cancelled']
            );
        });
    }
}
