<?php

namespace App\Services\Training;

use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\TrainingSchoolFee;
use App\Models\ProgramFeeCredit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\Notifications\NotificationService;

class TrainingProgramStatusService
{
    public function __construct(
        private \App\Services\Audit\PlatformAuditLogger $audit,
        private NotificationService $notificationService
    ) {}

    public function transitionToCancelled(TrainingProgram $program, bool $confirmCreditAll = false): void
    {
        $paidFees = TrainingSchoolFee::where('program_id', $program->id)
            ->where('amount_paid', '>', 0)
            ->get();

        if ($paidFees->isNotEmpty() && !$confirmCreditAll) {
            $count = $paidFees->count();
            $total = $paidFees->sum('amount_paid');

            throw ValidationException::withMessages([
                'status' => "This program has {$count} school(s) with approved payments totaling ₹{$total}. To proceed with cancellation and issue credits, you must confirm 'Credit all paid fees'.",
            ]);
        }

        DB::transaction(function () use ($program, $paidFees) {
            $registrations = TrainingRegistration::where('program_id', $program->id)
                ->whereIn('status', ['registered', 'waitlisted', 'confirmed', 'completed'])
                ->get();

            if ($registrations->isNotEmpty()) {
                TrainingRegistration::whereIn('id', $registrations->pluck('id'))->update([
                    'status' => 'cancelled',
                ]);
            }

            $issuedCredits = collect();
            
            foreach ($paidFees as $fee) {
                $feeAfter = app(TrainingSchoolFeeService::class)->recalculate($program, $fee->school_id);
                $reduction = round((float)$fee->total_due - (float)$feeAfter->total_due, 2);
                $paidBefore = (float)$fee->amount_paid;
                
                $creditAmount = min($reduction, $paidBefore);
                
                if ($creditAmount > 0) {
                    $credit = ProgramFeeCredit::create([
                        'creditable_type' => TrainingSchoolFee::class,
                        'creditable_id'   => $feeAfter->id,
                        'source_type'     => TrainingProgram::class,
                        'source_id'       => $program->id,
                        'amount'          => $creditAmount,
                        'reason'          => 'Program cancelled after payment',
                        'created_by_user_id' => auth()->id(),
                    ]);
                    $issuedCredits->push($credit);
                }
            }

            $program->update(['status' => 'cancelled']);

            // Notify affected schools
            $schoolIds = $registrations->pluck('school_id')->filter()->unique();
            $creditsBySchool = $issuedCredits->keyBy(function($c) {
                return $c->creditable?->school_id;
            });

            foreach ($schoolIds as $schoolId) {
                $creditForSchool = $creditsBySchool->get($schoolId);
                $replacements = [
                    'program_title' => $program->title,
                    'credit_line' => $creditForSchool && $creditForSchool->amount > 0
                        ? " A fee credit of ₹{$creditForSchool->amount} has been recorded to your school account."
                        : '',
                ];

                foreach (User::role(['school_admin', 'school_staff'])->where('tenant_id', $schoolId)->get() as $user) {
                    try {
                        $this->notificationService->notifyFromTemplate($user, 'training.program.cancelled', $replacements);
                    } catch (\Throwable) {
                    }
                }
            }

            $this->audit->training(
                $program,
                'training.program.cancelled',
                "Training program cancelled: {$program->title}",
                ['status' => 'cancelled']
            );
        });
    }
}
