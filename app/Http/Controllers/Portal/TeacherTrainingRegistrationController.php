<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\FeeReceipt;
use App\Models\Tenant;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Services\Membership\SchoolMembershipGate;
use App\Services\Training\TeacherTrainingEligibilityService;
use App\Support\TenantStorage;
use Illuminate\Http\Request;

class TeacherTrainingRegistrationController extends Controller
{
    public function register(Request $request, string $tenantId, TrainingProgram $program, TeacherTrainingEligibilityService $eligibility)
    {
        $teacher = $request->attributes->get('portalTeacher');
        $school = Tenant::findOrFail($tenantId);

        app(SchoolMembershipGate::class)->assertPaid($school);

        abort_if($program->tenant_id !== $school->parent_id, 403);
        abort_unless($program->allow_teacher_self_registration ?? true, 422, 'Self-registration is not enabled for this programme.');
        abort_if(
            TrainingRegistration::where('program_id', $program->id)->where('teacher_id', $teacher->id)->exists(),
            422,
            'You are already registered for this programme.',
        );

        abort_unless($eligibility->isEligible($program, $teacher), 422,
            $eligibility->ineligibilityReason($program, $teacher) ?? 'You are not eligible for this programme.');

        TrainingRegistration::create([
            'program_id'  => $program->id,
            'teacher_id'  => $teacher->id,
            'school_id'   => $school->id,
            'status'      => 'registered',
        ]);

        return back()->with('success', 'Registered successfully. Upload payment proof if a fee applies.');
    }

    public function uploadPayment(Request $request, string $tenantId, TrainingRegistration $registration)
    {
        $teacher = $request->attributes->get('portalTeacher');
        abort_if($registration->teacher_id !== $teacher->id, 403);

        $program = $registration->program;
        abort_unless($program?->hasFee(), 422, 'This programme does not require a fee.');

        $outstanding = $registration->outstandingBalance();
        abort_if($outstanding <= 0, 422, 'Fee already fully paid.');

        $data = $request->validate([
            'payment_proof'   => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'transaction_ref' => 'nullable|string|max:100',
            'amount'          => 'nullable|numeric|min:1|max:'.$outstanding,
        ]);

        $amount = round((float) ($data['amount'] ?? $outstanding), 2);
        $path = TenantStorage::storeUploadedFile(
            $request->file('payment_proof'),
            "training-payments/{$registration->school_id}"
        );

        FeeReceipt::supersedePriorForFeeable($registration);

        $receipt = FeeReceipt::create([
            'feeable_type'        => TrainingRegistration::class,
            'feeable_id'          => $registration->id,
            'file_path'           => $path,
            'transaction_ref'     => $data['transaction_ref'] ?? null,
            'payment_date'        => now()->toDateString(),
            'amount'              => $amount,
            'status'              => 'uploaded',
            'uploaded_by_user_id' => $request->user()->id,
        ]);

        $registration->update(['fee_receipt_id' => $receipt->id, 'fee_status' => 'proof_uploaded']);

        return back()->with('success', 'Payment proof uploaded. Awaiting Sahodaya verification.');
    }
}
