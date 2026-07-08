<?php

namespace App\Services\Membership;

use App\Models\Registration;
use App\Models\SahodayaProfile;
use App\Models\SchoolYearSubmission;
use App\Models\Tenant;
use App\Support\AcademicYear;

class RegistrationStatusService
{
    public function __construct(
        private MembershipFeeCalculator $feeCalculator,
        private SchoolMembershipNumberGenerator $membershipNumberGenerator,
    ) {}

    public function beginAnnualRegistration(Tenant $school, ?string $academicYear = null): Registration
    {
        $academicYear ??= AcademicYear::forSchool($school);
        $sahodaya = $school->parent;
        $profile = SahodayaProfile::where('tenant_id', $sahodaya->id)->firstOrFail();

        $existing = Registration::where('school_id', $school->id)
            ->where('academic_year', $academicYear)
            ->first();

        if ($existing) {
            return $existing;
        }

        $this->assertPriorYearResolved($school, $academicYear);

        $submission = $this->createSubmission($school, $academicYear, $profile);

        return $this->createRegistration($school, $academicYear, $profile, $submission);
    }

    private function createRegistration(
        Tenant $school,
        string $academicYear,
        SahodayaProfile $profile,
        SchoolYearSubmission $submission,
    ): Registration {
        $readyForPayment = ! $profile->requiresDataSubmission();

        $registration = Registration::create([
            'school_id'                 => $school->id,
            'academic_year'             => $academicYear,
            'reg_no'                    => $this->membershipNumberGenerator->generate($school, $academicYear),
            'registration_status'       => 'data_pending',
            'school_year_submission_id' => $submission->id,
            'membership_fee_amount'     => null,
        ]);

        if ($readyForPayment) {
            $this->feeCalculator->calculateAndApply($registration, $profile, $submission);
        }

        return $registration->fresh();
    }

    public function createSubmission(Tenant $school, string $academicYear, SahodayaProfile $profile): SchoolYearSubmission
    {
        return SchoolYearSubmission::firstOrCreate(
            [
                'school_id'     => $school->id,
                'academic_year' => $academicYear,
            ],
            [
                'full_records_status' => match ($profile->student_data_mode) {
                    'full_records' => 'pending',
                    default        => 'not_applicable',
                },
                'counts_status' => match ($profile->student_data_mode) {
                    'counts_only' => 'pending',
                    default       => 'not_applicable',
                },
                'teacher_status' => $profile->teacher_registration_enabled ? 'pending' : 'not_applicable',
            ],
        );
    }

    public function checkAndAdvanceToPayment(Registration $registration): void
    {
        $school = $registration->school;
        $profile = SahodayaProfile::where('tenant_id', $school->parent_id)->firstOrFail();
        $submission = $registration->submission;

        if (! $submission || ! $submission->allApplicableTracksApproved($profile)) {
            return;
        }

        $this->feeCalculator->calculateAndApply($registration, $profile, $submission);
    }

    public function ensureMembershipFee(Registration $registration): Registration
    {
        $registration = $this->ensureMembershipNumber($registration);

        if ($registration->membership_fee_amount !== null) {
            return $registration;
        }

        $school = $registration->school;
        $profile = SahodayaProfile::where('tenant_id', $school->parent_id)->firstOrFail();
        $submission = $registration->submission;

        if ($submission && ($submission->allApplicableTracksApproved($profile) || ! $profile->requiresDataSubmission())) {
            $this->feeCalculator->calculateAndApply($registration, $profile, $submission);

            return $registration->fresh();
        }

        return $registration;
    }

    public function ensureMembershipNumber(Registration $registration): Registration
    {
        if ($registration->reg_no) {
            return $registration;
        }

        $school = $registration->school;
        $registration->update([
            'reg_no' => $this->membershipNumberGenerator->generate($school, $registration->academic_year),
        ]);

        return $registration->fresh();
    }

    public function markDataRejected(Registration $registration): void
    {
        $registration->update(['registration_status' => 'data_rejected']);
    }

    public function markDataPending(Registration $registration): void
    {
        $registration->update(['registration_status' => 'data_pending']);
    }

    private function assertPriorYearResolved(Tenant $school, string $academicYear): void
    {
        $years = AcademicYear::options();
        $index = array_search($academicYear, $years, true);
        if ($index === false || ! isset($years[$index + 1])) {
            return;
        }

        $priorYear = $years[$index + 1];
        $priorReg = Registration::where('school_id', $school->id)
            ->where('academic_year', $priorYear)
            ->first();

        if ($priorReg && ! in_array($priorReg->registration_status, ['completed', 'approved'], true)) {
            throw new \RuntimeException('Prior year registration is unresolved. Contact your Sahodaya office.');
        }
    }
}
