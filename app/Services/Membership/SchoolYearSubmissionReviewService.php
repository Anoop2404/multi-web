<?php

namespace App\Services\Membership;

use App\Models\Registration;
use App\Models\SahodayaProfile;
use App\Models\SchoolYearSubmission;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Audit\DataChangeLogger;
use Illuminate\Validation\ValidationException;

class SchoolYearSubmissionReviewService
{
    /** @return array{field: string, reason_field: string} */
    public function trackFields(string $track): array
    {
        return match ($track) {
            'full_records' => ['field' => 'full_records_status', 'reason_field' => 'full_records_rejection_reason'],
            'counts'       => ['field' => 'counts_status', 'reason_field' => 'counts_rejection_reason'],
            'teachers'     => ['field' => 'teacher_status', 'reason_field' => 'teacher_rejection_reason'],
            default        => throw ValidationException::withMessages(['track' => 'Invalid track.']),
        };
    }

    public function submitTrack(SchoolYearSubmission $submission, Tenant $school, string $track): SchoolYearSubmission
    {
        ['field' => $field, 'reason_field' => $reasonField] = $this->trackFields($track);

        abort_unless(in_array($submission->{$field}, ['pending', 'rejected'], true), 403);

        $profile = SahodayaProfile::where('tenant_id', $school->parent_id)->firstOrFail();

        if ($track === 'full_records' && $profile->student_data_mode === 'full_records') {
            $count = Student::where('tenant_id', $school->id)->where('status', 'active')->count();
            if ($count < 1) {
                throw ValidationException::withMessages([
                    'track' => 'Add at least one active student under Records → Students before submitting.',
                ]);
            }
        }

        if ($track === 'counts' && $profile->student_data_mode === 'counts_only') {
            if ($submission->counts()->count() < 1) {
                throw ValidationException::withMessages([
                    'track' => 'Enter student counts before submitting.',
                ]);
            }
        }

        if ($track === 'teachers' && $profile->teacher_registration_enabled) {
            if ($submission->teachers()->count() < 1) {
                throw ValidationException::withMessages([
                    'track' => 'Add at least one teacher before submitting.',
                ]);
            }
        }

        $before = $submission->{$field};
        $submission->update([
            $field        => 'submitted',
            $reasonField  => null,
        ]);

        app(DataChangeLogger::class)->updated(
            $submission,
            "Annual registration track submitted for review: {$track}",
            [$field => ['old' => $before, 'new' => 'submitted']],
            $school->id,
            'membership',
            ['track' => $track],
        );

        app(MembershipNotifier::class)->dataSubmitted($school, $submission->academic_year);

        return $submission->fresh();
    }

    public function approveTrack(
        SchoolYearSubmission $submission,
        string $track,
        ?int $reviewerId = null,
    ): Registration {
        ['field' => $field] = $this->trackFields($track);

        abort_unless($submission->{$field} === 'submitted', 422, 'Track is not awaiting review.');

        $before = $submission->{$field};
        $submission->update([
            $field                 => 'approved',
            'reviewed_by_user_id'  => $reviewerId,
            'reviewed_at'          => now(),
        ]);

        app(DataChangeLogger::class)->updated(
            $submission,
            "Annual registration track approved: {$track}",
            [$field => ['old' => $before, 'new' => 'approved']],
            $submission->school_id,
            'membership',
            ['track' => $track, 'reviewer_id' => $reviewerId],
        );

        $registration = $submission->registration()->firstOrFail();
        $school = $submission->school;
        $profile = SahodayaProfile::where('tenant_id', $school->parent_id)->firstOrFail();
        $submission->refresh();

        if ($submission->allApplicableTracksApproved($profile)) {
            app(RegistrationStatusService::class)->checkAndAdvanceToPayment($registration->fresh());
            app(MembershipNotifier::class)->dataApproved($school, $submission->academic_year);
        }

        return $registration->fresh()->load('submission');
    }

    public function rejectTrack(
        SchoolYearSubmission $submission,
        string $track,
        string $reason,
        ?int $reviewerId = null,
    ): Registration {
        ['field' => $field, 'reason_field' => $reasonField] = $this->trackFields($track);

        abort_unless($submission->{$field} === 'submitted', 422, 'Track is not awaiting review.');

        $before = $submission->{$field};
        $submission->update([
            $field                 => 'rejected',
            $reasonField           => $reason,
            'reviewed_by_user_id'  => $reviewerId,
            'reviewed_at'          => now(),
        ]);

        app(DataChangeLogger::class)->updated(
            $submission,
            "Annual registration track rejected: {$track}",
            [$field => ['old' => $before, 'new' => 'rejected']],
            $submission->school_id,
            'membership',
            ['track' => $track, 'reason' => $reason, 'reviewer_id' => $reviewerId],
        );

        $registration = $submission->registration()->firstOrFail();
        app(RegistrationStatusService::class)->markDataRejected($registration);

        app(MembershipNotifier::class)->dataRejected(
            $submission->school,
            $submission->academic_year,
            $reason,
        );

        $service = app(\App\Services\Notifications\NotificationService::class);
        foreach (\App\Models\User::role(['school_admin', 'school_staff'])->where('tenant_id', $submission->school_id)->get() as $user) {
            $service->notifyFromTemplate($user, 'membership.data.rejected', [
                'academic_year' => $submission->academic_year,
                'reason'        => $reason,
            ], "/school-admin/{$submission->school_id}/registration");
        }

        return $registration->fresh()->load('submission');
    }
}
