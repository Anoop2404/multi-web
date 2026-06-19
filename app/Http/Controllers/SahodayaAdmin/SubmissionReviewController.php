<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\SchoolYearSubmission;
use App\Support\TenancyDatabase;

class SubmissionReviewController extends SahodayaAdminController
{
    public function index()
    {
        $submissions = SchoolYearSubmission::whereIn('school_id', TenancyDatabase::schoolIdsFor($this->sahodaya->id))
            ->with(['school', 'counts', 'students', 'registration'])
            ->orderByDesc('academic_year')
            ->orderBy('school_id')
            ->get()
            ->map(fn (SchoolYearSubmission $submission) => $this->enrichSubmission($submission));

        return $this->inertia('Sahodaya/Membership/Submissions', compact('submissions'));
    }

    public function show(string $tenantId, SchoolYearSubmission $submission)
    {
        abort_if($submission->school->parent_id !== $this->sahodaya->id, 403);

        $submission->load(['school', 'counts.classCategory', 'registration']);
        $submission = $this->enrichSubmission($submission);

        return $this->inertia('Sahodaya/Membership/SubmissionShow', [
            'submission' => $submission,
        ]);
    }

    private function enrichSubmission(SchoolYearSubmission $submission): SchoolYearSubmission
    {
        $submission->setAttribute('student_total', $this->studentTotal($submission));
        $submission->setAttribute('registration_status', $submission->registration?->registration_status);

        return $submission;
    }

    private function studentTotal(SchoolYearSubmission $submission): int
    {
        if ($submission->relationLoaded('counts') && $submission->counts->isNotEmpty()) {
            return (int) $submission->counts->sum('total_count');
        }

        if ($submission->relationLoaded('students')) {
            return $submission->students->count();
        }

        return (int) $submission->counts()->sum('total_count')
            ?: $submission->students()->count();
    }
}
