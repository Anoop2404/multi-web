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
                $school = $fee->school;
                if (! $school) {
                    continue;
                }

                // syncForSchool() is the canonical recalculation path and owns the
                // cancellation-credit delta calculation. The old call targeted a
                // non-existent recalculate() method and then attempted to create a second
                // credit manually.
                $feeAfter = app(McqSchoolFeeService::class)->syncForSchool(
                    $exam,
                    $school,
                    'Exam cancelled after payment',
                    auth()->id(),
                    null,
                );

                $credit = ProgramFeeCredit::query()
                    ->where('creditable_type', McqSchoolFee::class)
                    ->where('creditable_id', $feeAfter->id)
                    ->where('source_type', McqRegistration::class)
                    ->latest('id')
                    ->first();
                if ($credit) {
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
