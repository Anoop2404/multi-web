<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\SchoolYearSubmission;
use App\Models\Student;
use App\Models\SubmissionStudent;
use App\Models\SahodayaProfile;
use App\Services\Membership\SchoolYearSubmissionReviewService;
use App\Support\TenancyDatabase;
use App\Support\TenantStorage;
use Illuminate\Http\Request;

class SubmissionReviewController extends SahodayaAdminController
{
    public function index()
    {
        $submissions = SchoolYearSubmission::whereIn('school_id', TenancyDatabase::schoolIdsFor($this->sahodaya->id))
            ->with(['school', 'counts', 'students', 'teachers', 'registration'])
            ->orderByDesc('academic_year')
            ->orderBy('school_id')
            ->get()
            ->map(fn (SchoolYearSubmission $submission) => $this->enrichSubmission($submission));

        return $this->inertia('Sahodaya/Membership/Submissions', compact('submissions'));
    }

    public function show(string $tenantId, SchoolYearSubmission $submission)
    {
        abort_if($submission->school->parent_id !== $this->sahodaya->id, 403);

        $profile = SahodayaProfile::where('tenant_id', $this->sahodaya->id)->first();
        $submission->load(['school', 'counts.classCategory', 'teachers', 'registration']);

        $schoolStudents = Student::where('tenant_id', $submission->school_id)
            ->where('status', 'active')
            ->with('schoolClass.classCategory')
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->map(fn (Student $s) => [
                'id'     => $s->id,
                'name'   => $s->name,
                'reg_no' => $s->reg_no,
                'class'  => $s->schoolClass?->name,
                'category' => $s->schoolClass?->classCategory?->label,
            ]);

        return $this->inertia('Sahodaya/Membership/SubmissionShow', [
            'submission'     => $this->enrichSubmission($submission),
            'profile'        => $profile ? $profile->only(['student_data_mode', 'teacher_registration_enabled']) : null,
            'schoolStudents' => $schoolStudents,
        ]);
    }

    public function approveTrack(
        Request $request,
        string $tenantId,
        SchoolYearSubmission $submission,
        SchoolYearSubmissionReviewService $reviewService,
    ) {
        abort_if($submission->school->parent_id !== $this->sahodaya->id, 403);

        $data = $request->validate(['track' => 'required|in:full_records,counts,teachers']);

        $reviewService->approveTrack($submission, $data['track'], $request->user()?->id);

        return back()->with('success', 'Submission track approved.');
    }

    public function rejectTrack(
        Request $request,
        string $tenantId,
        SchoolYearSubmission $submission,
        SchoolYearSubmissionReviewService $reviewService,
    ) {
        abort_if($submission->school->parent_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'track'  => 'required|in:full_records,counts,teachers',
            'reason' => 'required|string|max:2000',
        ]);

        $reviewService->rejectTrack($submission, $data['track'], $data['reason'], $request->user()?->id);

        return back()->with('success', 'Submission track rejected. School can correct and resubmit.');
    }

    public function showSubmissionStudentImage(string $tenantId, SubmissionStudent $student)
    {
        $student->loadMissing('submission.school');
        abort_if($student->submission->school->parent_id !== $this->sahodaya->id, 403);
        abort_unless($student->image_path, 404);

        return TenantStorage::downloadResponse($student->submission->school, $student->image_path);
    }

    private function enrichSubmission(SchoolYearSubmission $submission): SchoolYearSubmission
    {
        $submission->setAttribute('student_total', $this->studentTotal($submission));
        $submission->setAttribute('registration_status', $submission->registration?->registration_status);
        $submission->setAttribute('pending_tracks', $this->pendingTracks($submission));

        return $submission;
    }

    /** @return list<string> */
    private function pendingTracks(SchoolYearSubmission $submission): array
    {
        $pending = [];
        foreach (['full_records', 'counts', 'teachers'] as $track) {
            $field = match ($track) {
                'full_records' => 'full_records_status',
                'counts'       => 'counts_status',
                default        => 'teacher_status',
            };
            if ($submission->{$field} === 'submitted') {
                $pending[] = $track;
            }
        }

        return $pending;
    }

    private function studentTotal(SchoolYearSubmission $submission): int
    {
        $profile = SahodayaProfile::where('tenant_id', $submission->school?->parent_id)->first();

        if ($profile?->student_data_mode === 'full_records') {
            return (int) Student::where('tenant_id', $submission->school_id)->where('status', 'active')->count();
        }

        if ($submission->relationLoaded('counts') && $submission->counts->isNotEmpty()) {
            return (int) $submission->counts->sum('total_count');
        }

        if ($submission->relationLoaded('students') && $submission->students->isNotEmpty()) {
            return $submission->students->count();
        }

        return (int) $submission->counts()->sum('total_count')
            ?: $submission->students()->count();
    }
}
