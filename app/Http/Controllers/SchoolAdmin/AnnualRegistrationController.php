<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\SahodayaProfile;
use App\Models\SahodayaRegistrationWindow;
use App\Models\SchoolClass;
use App\Models\SchoolYearStudentCount;
use App\Models\SubmissionStudent;
use App\Models\SubmissionTeacher;
use App\Services\Audit\DataChangeLogger;
use App\Services\Audit\UploadBackupService;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Services\Membership\MembershipNotifier;
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

        $payments = $registration
            ? MembershipPayment::where('school_id', $this->school->id)
                ->where('academic_year', $academicYear)
                ->orderByDesc('created_at')
                ->get()
            : collect();

        $profilePayload = $profile ? array_merge($profile->toArray(), [
            'payment_details_text' => $profile->paymentDetailsText(),
        ]) : null;

        return $this->inertia('School/Registration/Index', [
            'academicYear'       => $academicYear,
            'registration'       => $registration?->load('submission'),
            'profile'            => $profilePayload,
            'registrationWindow' => $window,
            'payments'           => $payments,
            'canBegin'           => $profile && ! $registration && ! empty($this->school->school_prefix),
            'membershipFeePreview' => $profile && $profile->membership_fee_type === 'fixed'
                ? $profile->fixed_membership_fee_amount
                : null,
        ]);
    }

    public function begin(RegistrationStatusService $service)
    {
        $academicYear = AcademicYear::forSchool($this->school);
        $alreadyStarted = Registration::where('school_id', $this->school->id)
            ->where('academic_year', $academicYear)
            ->exists();

        $registration = $service->beginAnnualRegistration($this->school);

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

        return $this->inertia('School/Registration/Students', [
            'registration' => $registration,
            'submission'   => $submission,
            'categories'   => $this->classCategories()->values(),
            'classes'      => $this->schoolClasses(),
            'students'     => $submission->students()
                ->with('schoolClass.classCategory')
                ->orderBy('school_class_id')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeStudent(Request $request)
    {
        $registration = $this->currentRegistration();
        $submission = $registration->submission;
        abort_unless(in_array($submission->full_records_status, ['pending', 'rejected']), 403);

        $data = $request->validate([
            'school_class_id' => [
                'required',
                Rule::exists('school_classes', 'id')->where('tenant_id', $this->school->id),
            ],
            'name'           => 'required|string|max:255',
            'section'        => 'nullable|string|max:10',
            'gender'         => 'nullable|in:male,female,other',
            'dob'            => 'nullable|date',
            'guardian_name'  => 'nullable|string|max:255',
            'guardian_phone' => 'nullable|string|max:30',
            'image'          => 'nullable|image|max:2048',
        ]);

        $schoolClass = SchoolClass::where('tenant_id', $this->school->id)->findOrFail($data['school_class_id']);
        $data['class'] = $schoolClass->name;

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store("submissions/{$this->school->id}", 'public');
        }

        $submission->students()->create($data);

        return back()->with('success', 'Student added.');
    }

    public function destroyStudent(string $tenantId, SubmissionStudent $student)
    {
        $registration = $this->currentRegistration();
        abort_if($student->school_year_submission_id !== $registration->submission->id, 403);
        abort_unless(in_array($registration->submission->full_records_status, ['pending', 'rejected']), 403);

        $student->delete();

        return back()->with('success', 'Student removed.');
    }

    public function counts(EffectiveMasterDataResolver $resolver)
    {
        $registration = $this->currentRegistration();
        $sahodayaId = $this->school->parent_id;
        $categories = $resolver->classCategories($sahodayaId);
        $submission = $registration->submission;
        $existing = $submission->counts()->get()->keyBy('class_category_id');

        return $this->inertia('School/Registration/Counts', [
            'registration' => $registration,
            'submission'   => $submission,
            'categories'   => $categories,
            'counts'       => $existing,
        ]);
    }

    public function saveCounts(Request $request)
    {
        $registration = $this->currentRegistration();
        $submission = $registration->submission;
        abort_unless(in_array($submission->counts_status, ['pending', 'rejected']), 403);

        $data = $request->validate([
            'counts' => 'required|array',
            'counts.*.class_category_id' => 'required|exists:class_categories,id',
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

    public function storeTeacher(Request $request, EffectiveMasterDataResolver $resolver)
    {
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

    public function destroyTeacher(string $tenantId, SubmissionTeacher $teacher)
    {
        $registration = $this->currentRegistration();
        abort_if($teacher->school_year_submission_id !== $registration->submission->id, 403);
        $teacher->delete();

        return back()->with('success', 'Teacher removed.');
    }

    public function submitTrack(Request $request)
    {
        $registration = $this->currentRegistration();
        $submission = $registration->submission;

        $data = $request->validate(['track' => 'required|in:full_records,counts,teachers']);

        $field = match ($data['track']) {
            'full_records' => 'full_records_status',
            'counts'       => 'counts_status',
            'teachers'     => 'teacher_status',
        };

        abort_unless(in_array($submission->{$field}, ['pending', 'rejected']), 403);

        $before = $submission->{$field};
        $submission->update([
            $field => 'approved',
            str_replace('_status', '_rejection_reason', $field) => null,
        ]);

        app(DataChangeLogger::class)->updated(
            $submission,
            "Annual registration track submitted: {$data['track']}",
            [$field => ['old' => $before, 'new' => 'approved']],
            $this->school->id,
            'membership',
            ['track' => $data['track']],
        );

        $profile = SahodayaProfile::where('tenant_id', $this->school->parent_id)->firstOrFail();
        $submission->refresh();

        if ($submission->allApplicableTracksApproved($profile)) {
            app(RegistrationStatusService::class)->checkAndAdvanceToPayment($registration->fresh());
        } elseif ($registration->registration_status === 'data_rejected') {
            $registration->update(['registration_status' => 'data_pending']);
        }

        return back()->with('success', 'Section submitted. Complete all sections to unlock membership payment.');
    }

    public function payment()
    {
        $registration = app(RegistrationStatusService::class)
            ->ensureMembershipFee($this->currentRegistration());
        abort_unless(in_array($registration->registration_status, ['payment_pending', 'payment_rejected']), 403);

        $profile = SahodayaProfile::where('tenant_id', $this->school->parent_id)->first();

        return $this->inertia('School/Registration/Payment', [
            'registration' => $registration,
            'profile'      => $profile ? array_merge($profile->toArray(), [
                'payment_details_text' => $profile->paymentDetailsText(),
            ]) : null,
            'payments'     => MembershipPayment::where('school_id', $this->school->id)
                ->where('academic_year', $registration->academic_year)
                ->orderByDesc('created_at')
                ->get(),
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

    private function currentRegistration(): Registration
    {
        $registration = Registration::where('school_id', $this->school->id)
            ->where('academic_year', AcademicYear::forSchool($this->school))
            ->with('submission')
            ->firstOrFail();

        return $registration;
    }
}
