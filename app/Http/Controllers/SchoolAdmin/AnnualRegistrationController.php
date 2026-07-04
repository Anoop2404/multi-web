<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\MembershipPayment;
use App\Models\ClassCategory;
use App\Models\Registration;
use App\Models\SahodayaProfile;
use App\Models\SahodayaRegistrationWindow;
use App\Models\SchoolClass;
use App\Models\SchoolYearStudentCount;
use App\Models\Student;
use App\Services\Membership\SchoolYearSubmissionReviewService;
use App\Models\SubmissionStudent;
use App\Models\SubmissionTeacher;
use App\Services\Audit\DataChangeLogger;
use App\Services\Audit\UploadBackupService;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Services\Membership\FeeReceiptService;
use App\Services\Membership\MembershipNotifier;
use App\Services\Membership\MembershipRegistrationWindowService;
use App\Services\Membership\RegistrationStatusService;
use App\Support\AcademicYear;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnnualRegistrationController extends SchoolAdminController
{
    public function index(EffectiveMasterDataResolver $resolver)
    {
        $academicYear = AcademicYear::forSchool($this->school);
        $sahodaya = $this->school->parent;
        $profile = SahodayaProfile::where('tenant_id', $sahodaya->id)->first();
        $registration = Registration::where('school_id', $this->school->id)->where('academic_year', $academicYear)->first();
        if ($registration) {
            $registration = app(RegistrationStatusService::class)->ensureMembershipNumber($registration);
            if ($registration->membership_fee_amount === null) {
                $registration = app(RegistrationStatusService::class)->ensureMembershipFee($registration);
            }
        }
        $window = $profile
            ? SahodayaRegistrationWindow::where('sahodaya_id', $sahodaya->id)->where('academic_year', $academicYear)->first()
            : null;
        $windowService = app(MembershipRegistrationWindowService::class);
        $windowBlockReason = $windowService->blockReason($window);

        $payments = $registration
            ? MembershipPayment::where('school_id', $this->school->id)
                ->where('academic_year', $academicYear)
                ->where('status', '!=', 'superseded')
                ->orderByDesc('created_at')
                ->get()
            : collect();

        $yearOptions = AcademicYear::options();
        $priorYear = null;
        $currentIndex = array_search($academicYear, $yearOptions, true);
        if ($currentIndex !== false && isset($yearOptions[$currentIndex + 1])) {
            $priorYear = $yearOptions[$currentIndex + 1];
        }

        $priorRegistration = $priorYear
            ? Registration::where('school_id', $this->school->id)->where('academic_year', $priorYear)->first()
            : null;

        $isRenewal = ! $registration
            && $priorRegistration
            && in_array($priorRegistration->registration_status, ['completed', 'approved'], true);

        $priorYearSummary = $priorRegistration ? [
            'academic_year'        => $priorRegistration->academic_year,
            'reg_no'               => $priorRegistration->reg_no,
            'registration_status'  => $priorRegistration->registration_status,
            'membership_fee_amount'=> $priorRegistration->membership_fee_amount,
        ] : null;

        $profilePayload = $profile ? array_merge($profile->toArray(), [
            'payment_details_text' => $profile->paymentDetailsText(),
            'membership_fee_configured' => $profile->membershipFeeConfigured($academicYear),
            'requires_membership_payment' => $profile->requiresMembershipPayment(),
        ]) : null;

        $feeNotConfigured = $profile && ! $profile->membershipFeeConfigured($academicYear);

        return $this->inertia('School/Registration/Index', [
            'academicYear'       => $academicYear,
            'registration'       => $registration?->load('submission'),
            'profile'            => $profilePayload,
            'registrationWindow' => $windowService->displayPayload($window),
            'registrationWindowBlockReason' => $windowBlockReason,
            'membershipFeeNotConfigured' => $feeNotConfigured,
            'payments'           => $payments,
            'canBegin'           => $profile
                && ! $registration
                && ! empty($this->school->school_prefix)
                && ! $windowBlockReason
                && ! $feeNotConfigured,
            'isRenewal'          => $isRenewal,
            'priorYearSummary'   => $priorYearSummary,
            'membershipFeePreview' => $profile && $profile->membership_fee_type === 'none'
                ? 0
                : ($profile && $profile->membership_fee_type === 'fixed'
                ? $profile->fixed_membership_fee_amount
                : ($profile && $profile->membership_fee_type === 'variable_by_student_count'
                    ? app(\App\Services\Membership\MembershipFeeCalculator::class)->estimateFeeForSchool($this->school, $academicYear)
                    : null)),
            'membershipFeeEstimateStudents' => $profile && $profile->membership_fee_type === 'variable_by_student_count'
                ? app(\App\Services\Membership\MembershipFeeCalculator::class)->estimateStudentCount($this->school, $academicYear)
                : null,
            'trackStatus' => $registration?->submission ? [
                'full_records' => $registration->submission->full_records_status,
                'counts'       => $registration->submission->counts_status,
                'teachers'     => $registration->submission->teacher_status,
            ] : null,
            'trackRejectionReasons' => $registration?->submission ? array_filter([
                'full_records' => $registration->submission->full_records_rejection_reason,
                'counts'       => $registration->submission->counts_rejection_reason,
                'teachers'     => $registration->submission->teacher_rejection_reason,
            ]) : null,
            'membershipReceiptPaymentId' => $payments->firstWhere('status', 'verified')?->id,
        ]);
    }

    public function begin(RegistrationStatusService $service, MembershipRegistrationWindowService $windowService)
    {
        $academicYear = AcademicYear::forSchool($this->school);
        $profile = SahodayaProfile::where('tenant_id', $this->school->parent_id)->first();
        abort_unless($profile && $profile->membershipFeeConfigured($academicYear), 422, 'Membership fees are not configured yet. Contact your Sahodaya office.');

        $window = $windowService->forSchool($this->school, $academicYear);
        if ($reason = $windowService->blockReason($window)) {
            return redirect("/school-admin/{$this->school->id}/registration")
                ->with('error', $reason);
        }

        $alreadyStarted = Registration::where('school_id', $this->school->id)
            ->where('academic_year', $academicYear)
            ->exists();

        try {
            $registration = $service->beginAnnualRegistration($this->school);
        } catch (\RuntimeException $e) {
            return redirect("/school-admin/{$this->school->id}/registration")
                ->with('error', $e->getMessage());
        }

        if (! $alreadyStarted) {
            app(DataChangeLogger::class)->created(
                $registration,
                "Annual membership started: {$registration->reg_no}",
                $this->school->id,
                'membership',
            );
        }

        $message = $alreadyStarted
            ? "Annual membership in progress. Membership No: {$registration->reg_no}"
            : "Annual membership started. Membership No: {$registration->reg_no}";

        return redirect("/school-admin/{$this->school->id}/registration")
            ->with('success', $message);
    }

    public function students(EffectiveMasterDataResolver $resolver)
    {
        $registration = $this->currentRegistration();
        $submission = $registration->submission;

        $students = Student::where('tenant_id', $this->school->id)
            ->where('status', 'active')
            ->with('schoolClass.classCategory')
            ->orderBy('name')
            ->get()
            ->map(fn (Student $student) => [
                'id'           => $student->id,
                'name'         => $student->name,
                'reg_no'       => $student->reg_no,
                'gender'       => $student->gender,
                'dob'          => $student->dob?->format('Y-m-d'),
                'school_class' => $student->schoolClass ? [
                    'name'          => $student->schoolClass->name,
                    'class_category' => $student->schoolClass->classCategory ? [
                        'label' => $student->schoolClass->classCategory->label,
                    ] : null,
                ] : null,
            ]);

        return $this->inertia('School/Registration/Students', [
            'registration' => $registration,
            'submission'   => $submission,
            'students'     => $students,
            'studentTotal' => $students->count(),
        ]);
    }

    public function storeStudent(Request $request)
    {
        return redirect("/school-admin/{$this->school->id}/students")
            ->with('info', 'Student records are managed under Records → Students. Return here to submit for Sahodaya review.');
    }

    public function destroyStudent(string $tenantId, int $student)
    {
        return redirect("/school-admin/{$this->school->id}/students")
            ->with('info', 'Student records are managed under Records → Students.');
    }

    public function counts(EffectiveMasterDataResolver $resolver)
    {
        $registration = $this->currentRegistration();
        $sahodayaId = $this->school->parent_id;
        $categories = $resolver->classCategories($sahodayaId);
        $submission = $registration->submission;
        $existing = $submission->counts()->get()->keyBy('class_category_id');
        $dbStudentCount = Student::where('tenant_id', $this->school->id)->where('status', 'active')->count();
        $submittedTotal = (int) $existing->sum('total_count');

        return $this->inertia('School/Registration/Counts', [
            'registration' => $registration,
            'submission'   => $submission,
            'categories'   => $categories,
            'counts'       => $existing,
            'dbStudentCount' => $dbStudentCount,
            'countMismatch' => $dbStudentCount > 0 && $submittedTotal > 0
                && abs($dbStudentCount - $submittedTotal) / max($dbStudentCount, 1) > 0.1,
        ]);
    }

    public function saveCounts(Request $request, MembershipRegistrationWindowService $windowService)
    {
        $this->assertRegistrationEditAllowed($windowService);
        $registration = $this->currentRegistration();
        $submission = $registration->submission;
        abort_unless(in_array($submission->counts_status, ['pending', 'rejected']), 403);

        $data = $request->validate([
            'counts' => 'required|array',
            'counts.*.class_category_id' => ['required', Rule::exists(ClassCategory::class, 'id')],
            'counts.*.male_count'        => 'required|integer|min:0',
            'counts.*.female_count'      => 'required|integer|min:0',
            'counts.*.total_count'       => 'required|integer|min:0',
        ]);

        foreach ($data['counts'] as $row) {
            SchoolYearStudentCount::updateOrCreate(
                ['school_year_submission_id' => $submission->id, 'class_category_id' => $row['class_category_id']],
                $row
            );
        }

        app(DataChangeLogger::class)->event(
            'updated',
            'Annual registration student counts saved',
            $this->school->id,
            'membership',
            $submission,
            ['counts_rows' => count($data['counts'])],
        );

        return back()->with('success', 'Counts saved.');
    }

    public function teachers(EffectiveMasterDataResolver $resolver)
    {
        $registration = $this->currentRegistration();
        $submission = $registration->submission;

        return $this->inertia('School/Registration/Teachers', [
            'registration'  => $registration,
            'submission'    => $submission,
            'teachers'      => $submission->teachers()->with('teachingType')->get(),
            'teachingTypes' => $resolver->teachingTypes($this->school->parent_id),
        ]);
    }

    public function storeTeacher(Request $request, EffectiveMasterDataResolver $resolver, MembershipRegistrationWindowService $windowService)
    {
        $this->assertRegistrationEditAllowed($windowService);
        $registration = $this->currentRegistration();
        $submission = $registration->submission;
        abort_unless(in_array($submission->teacher_status, ['pending', 'rejected']), 403);

        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'subject'          => 'nullable|string|max:100',
            'teaching_type_id' => ['nullable', Rule::exists('teaching_types', 'id')],
        ]);

        $submission->teachers()->create($data);

        return back()->with('success', 'Teacher added.');
    }

    public function destroyTeacher(string $tenantId, SubmissionTeacher $teacher, MembershipRegistrationWindowService $windowService)
    {
        $this->assertRegistrationEditAllowed($windowService);
        $registration = $this->currentRegistration();
        abort_if($teacher->school_year_submission_id !== $registration->submission->id, 403);
        $teacher->delete();

        return back()->with('success', 'Teacher removed.');
    }

    public function submitTrack(Request $request, SchoolYearSubmissionReviewService $reviewService, MembershipRegistrationWindowService $windowService)
    {
        $this->assertRegistrationEditAllowed($windowService);
        $registration = $this->currentRegistration();
        $submission = $registration->submission;

        $data = $request->validate(['track' => 'required|in:full_records,counts,teachers']);

        $reviewService->submitTrack($submission, $this->school, $data['track']);

        $profile = SahodayaProfile::where('tenant_id', $this->school->parent_id)->firstOrFail();
        $submission->refresh();

        if ($submission->allApplicableTracksApproved($profile)) {
            app(RegistrationStatusService::class)->checkAndAdvanceToPayment($registration->fresh());
        } elseif ($registration->registration_status === 'data_rejected') {
            $registration->update(['registration_status' => 'data_pending']);
        }

        return back()->with('success', 'Submitted for Sahodaya review. You will be notified when approved.');
    }

    public function payment()
    {
        $registration = app(RegistrationStatusService::class)
            ->ensureMembershipFee($this->currentRegistration());
        abort_unless(in_array($registration->registration_status, ['payment_pending', 'payment_rejected']), 403);

        $profile = SahodayaProfile::where('tenant_id', $this->school->parent_id)->first();
        $slab = \App\Models\MembershipFeeSlab::where('sahodaya_id', $this->school->parent_id)
            ->where('academic_year', $registration->academic_year)
            ->orderByDesc('min_students')
            ->first();
        $isOverdue = $slab?->due_date && now()->startOfDay()->gt($slab->due_date);
        $lateFee = ($isOverdue && $slab?->late_fee_amount) ? (float) $slab->late_fee_amount : 0;

        return $this->inertia('School/Registration/Payment', [
            'registration' => $registration,
            'profile'      => $profile ? array_merge($profile->toArray(), [
                'payment_details_text' => $profile->paymentDetailsText(),
            ]) : null,
            'payments'     => MembershipPayment::where('school_id', $this->school->id)
                ->where('academic_year', $registration->academic_year)
                ->where('status', '!=', 'superseded')
                ->orderByDesc('created_at')
                ->get(),
            'paymentDueDate' => $slab?->due_date?->format('Y-m-d'),
            'paymentOverdue' => (bool) $isOverdue,
            'lateFeeAmount'  => $lateFee,
            'totalDue'       => (float) ($registration->membership_fee_amount ?? 0) + $lateFee,
        ]);
    }

    public function uploadPayment(Request $request, MembershipNotifier $notifier)
    {
        $registration = app(RegistrationStatusService::class)
            ->ensureMembershipFee($this->currentRegistration());
        abort_unless(in_array($registration->registration_status, ['payment_pending', 'payment_rejected']), 403);

        if ($registration->membership_fee_amount === null) {
            return back()->with('error', 'Membership fee is not configured yet. Please contact your Sahodaya office.');
        }

        $data = $request->validate([
            'payment_method'  => 'nullable|string|max:50',
            'transaction_ref' => 'nullable|string|max:100',
            'payment_proof'   => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $file = $request->file('payment_proof');
        $backup = app(UploadBackupService::class)->store(
            $file,
            'payment_proof',
            $this->school->id,
            null,
            $request->user()->id,
            ['academic_year' => $registration->academic_year],
        );

        $path = TenantStorage::storeUploadedFile($file, "payments/{$this->school->id}");

        $superseded = MembershipPayment::where('school_id', $this->school->id)
            ->where('academic_year', $registration->academic_year)
            ->whereIn('status', ['submitted', 'rejected'])
            ->get();

        $payment = MembershipPayment::create([
            'school_id'           => $this->school->id,
            'academic_year'       => $registration->academic_year,
            'registration_id'     => $registration->id,
            'amount'              => $registration->membership_fee_amount,
            'payment_proof_path'  => $path,
            'payment_method'      => $data['payment_method'] ?? null,
            'transaction_ref'     => $data['transaction_ref'] ?? null,
            'uploaded_by_user_id' => $request->user()->id,
            'status'              => 'submitted',
        ]);

        foreach ($superseded as $old) {
            $old->update([
                'status' => 'superseded',
                'superseded_by_payment_id' => $payment->id,
            ]);
        }

        app(FeeReceiptService::class)->createForMembershipPayment($payment);

        $backup->update([
            'related_type' => $payment->getMorphClass(),
            'related_id'   => $payment->id,
        ]);

        $regBefore = $registration->registration_status;
        $registration->update(['registration_status' => 'payment_submitted']);

        app(DataChangeLogger::class)->created(
            $payment,
            'Membership payment proof submitted',
            $this->school->id,
            'membership',
            [
                'amount'          => $payment->amount,
                'transaction_ref' => $payment->transaction_ref,
                'backup_id'       => $backup->id,
            ],
        );

        app(DataChangeLogger::class)->updated(
            $registration,
            'Registration moved to payment submitted',
            ['registration_status' => ['old' => $regBefore, 'new' => 'payment_submitted']],
            $this->school->id,
            'membership',
        );
        $notifier->paymentSubmitted(
            $this->school,
            $registration->academic_year,
            $registration->membership_fee_amount !== null ? (float) $registration->membership_fee_amount : null,
            $data['transaction_ref'] ?? null,
            $data['payment_method'] ?? null,
        );

        return redirect("/school-admin/{$this->school->id}/registration")
            ->with('success', 'Payment proof submitted. Sahodaya will verify your payment.');
    }

    public function paymentProof(MembershipPayment $payment)
    {
        abort_unless($payment->school_id === $this->school->id, 403);
        abort_unless($payment->payment_proof_path, 404);

        return TenantStorage::downloadResponse($this->school, $payment->payment_proof_path);
    }

    public function showSubmissionStudentImage(string $tenantId, SubmissionStudent $student)
    {
        $registration = $this->currentRegistration();
        abort_if($student->school_year_submission_id !== $registration->submission->id, 403);
        abort_unless($student->image_path, 404);

        return TenantStorage::downloadResponse($this->school, $student->image_path);
    }

    private function submissionStudentPayload(SubmissionStudent $student): array
    {
        $data = $student->toArray();
        $data['image_url'] = $student->imageUrl($this->school->id);

        return $data;
    }

    private function currentRegistration(): Registration
    {
        $registration = Registration::where('school_id', $this->school->id)
            ->where('academic_year', AcademicYear::forSchool($this->school))
            ->with('submission')
            ->firstOrFail();

        return $registration;
    }

    private function assertRegistrationEditAllowed(MembershipRegistrationWindowService $windowService): void
    {
        $window = $windowService->forSchool($this->school, AcademicYear::forSchool($this->school));
        if ($reason = $windowService->editBlockReason($window)) {
            abort(403, $reason);
        }
    }
}
