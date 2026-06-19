<?php

namespace App\Http\Controllers\Api\V1\School;

use App\Http\Resources\MembershipPaymentResource;
use App\Http\Resources\RegistrationResource;
use App\Models\ClassCategory;
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

class RegistrationApiController extends SchoolApiController
{
    public function index(EffectiveMasterDataResolver $resolver)
    {
        $academicYear = AcademicYear::forSchool($this->school);
        $sahodaya = $this->school->parent;
        $profile = $sahodaya
            ? SahodayaProfile::where('tenant_id', $sahodaya->id)->first()
            : null;
        $registration = Registration::where('school_id', $this->school->id)
            ->where('academic_year', $academicYear)
            ->first();

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

        return $this->ok([
            'academic_year' => $academicYear,
            'registration'  => $registration
                ? RegistrationResource::make($registration->load('submission'))
                : null,
            'profile' => $profile ? [
                'student_data_mode'           => $profile->student_data_mode,
                'teacher_registration_enabled'=> $profile->teacher_registration_enabled,
                'membership_fee_type'         => $profile->membership_fee_type,
                'payment_details_text'        => $profile->paymentDetailsText(),
            ] : null,
            'registration_window' => $window,
            'payments'              => MembershipPaymentResource::collection($payments),
            'can_begin'             => $profile && ! $registration && filled($this->school->school_prefix),
            'categories'            => $resolver->classCategories($sahodaya?->id)->values(),
            'classes'               => SchoolClass::where('tenant_id', $this->school->id)->active()->orderBy('display_order')->get(),
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

        return $this->message(
            $alreadyStarted ? 'Annual membership in progress.' : 'Annual membership started.',
            200,
            RegistrationResource::make($registration->load('submission')),
        );
    }

    public function storeSubmissionStudent(Request $request)
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
            $file = $request->file('image');
            $data['image_path'] = TenantStorage::storeSubmissionImage($file, $this->school->id);
        }
        unset($data['image']);

        $student = $submission->students()->create($data);

        return $this->ok($student, 201);
    }

    public function submissionStudents(EffectiveMasterDataResolver $resolver)
    {
        $registration = $this->currentRegistration();
        $submission = $registration->submission;

        return $this->ok([
            'registration' => RegistrationResource::make($registration),
            'submission'   => $submission,
            'categories'   => $resolver->classCategories($this->school->parent_id)->values(),
            'classes'      => SchoolClass::where('tenant_id', $this->school->id)->active()->orderBy('display_order')->get(),
            'students'     => $submission->students()
                ->with('schoolClass.classCategory')
                ->orderBy('school_class_id')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function destroySubmissionStudent(string $tenantId, string $studentId)
    {
        $registration = $this->currentRegistration();
        $student = SubmissionStudent::where('school_year_submission_id', $registration->submission->id)
            ->findOrFail($studentId);
        abort_unless(in_array($registration->submission->full_records_status, ['pending', 'rejected']), 403);

        $student->delete();

        return $this->message('Student removed.');
    }

    public function counts(EffectiveMasterDataResolver $resolver)
    {
        $registration = $this->currentRegistration();
        $submission = $registration->submission;
        $categories = $resolver->classCategories($this->school->parent_id);
        $existing = $submission->counts()->get()->keyBy('class_category_id');

        return $this->ok([
            'registration' => RegistrationResource::make($registration),
            'submission'   => $submission,
            'categories'   => $categories->values(),
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

        return $this->message('Counts saved.');
    }

    public function storeTeacher(Request $request)
    {
        $registration = $this->currentRegistration();
        $submission = $registration->submission;
        abort_unless(in_array($submission->teacher_status, ['pending', 'rejected']), 403);

        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'subject'          => 'nullable|string|max:100',
            'teaching_type_id' => ['nullable', Rule::exists('teaching_types', 'id')],
        ]);

        $teacher = $submission->teachers()->create($data);

        return $this->ok($teacher, 201);
    }

    public function teachers(EffectiveMasterDataResolver $resolver)
    {
        $registration = $this->currentRegistration();
        $submission = $registration->submission;

        return $this->ok([
            'registration'  => RegistrationResource::make($registration),
            'submission'    => $submission,
            'teachers'      => $submission->teachers()->with('teachingType')->get(),
            'teaching_types'=> $resolver->teachingTypes($this->school->parent_id),
        ]);
    }

    public function destroyTeacher(string $tenantId, string $teacherId)
    {
        $registration = $this->currentRegistration();
        $teacher = SubmissionTeacher::where('school_year_submission_id', $registration->submission->id)
            ->findOrFail($teacherId);
        $teacher->delete();

        return $this->message('Teacher removed.');
    }

    public function submitTrack(Request $request, RegistrationStatusService $service)
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

        $submission->update([
            $field => 'approved',
            str_replace('_status', '_rejection_reason', $field) => null,
        ]);

        $profile = SahodayaProfile::where('tenant_id', $this->school->parent_id)->firstOrFail();
        $submission->refresh();

        if ($submission->allApplicableTracksApproved($profile)) {
            $service->checkAndAdvanceToPayment($registration->fresh());
        } elseif ($registration->registration_status === 'data_rejected') {
            $registration->update(['registration_status' => 'data_pending']);
        }

        return $this->message('Section submitted.', 200, RegistrationResource::make($registration->fresh()->load('submission')));
    }

    public function uploadPayment(Request $request, MembershipNotifier $notifier)
    {
        $registration = app(RegistrationStatusService::class)
            ->ensureMembershipFee($this->currentRegistration());
        abort_unless(in_array($registration->registration_status, ['payment_pending', 'payment_rejected']), 403);

        $data = $request->validate([
            'payment_method'  => 'nullable|string|max:50',
            'transaction_ref' => 'nullable|string|max:100',
            'payment_proof'   => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $file = $request->file('payment_proof');
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

        $registration->update(['registration_status' => 'payment_submitted']);
        $notifier->paymentSubmitted(
            $this->school,
            $registration->academic_year,
            $registration->membership_fee_amount !== null ? (float) $registration->membership_fee_amount : null,
            $data['transaction_ref'] ?? null,
            $data['payment_method'] ?? null,
        );

        return $this->message('Payment proof submitted.', 201, MembershipPaymentResource::make($payment));
    }

    public function paymentProof(string $tenantId, string $paymentId)
    {
        $payment = MembershipPayment::where('school_id', $this->school->id)->findOrFail($paymentId);
        abort_unless($payment->payment_proof_path, 404);

        return TenantStorage::downloadResponse($this->school, $payment->payment_proof_path);
    }

    private function currentRegistration(): Registration
    {
        return Registration::where('school_id', $this->school->id)
            ->where('academic_year', AcademicYear::forSchool($this->school))
            ->with('submission')
            ->firstOrFail();
    }
}
