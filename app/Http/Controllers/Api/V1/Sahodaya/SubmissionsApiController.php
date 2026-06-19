<?php

namespace App\Http\Controllers\Api\V1\Sahodaya;

use App\Http\Resources\SubmissionResource;
use App\Models\SchoolYearSubmission;
use App\Support\TenancyDatabase;

class SubmissionsApiController extends SahodayaApiController
{
    public function index()
    {
        $submissions = SchoolYearSubmission::whereIn('school_id', TenancyDatabase::schoolIdsFor($this->sahodaya->id))
            ->with(['school', 'counts', 'students', 'registration'])
            ->orderByDesc('academic_year')
            ->orderBy('school_id')
            ->get()
            ->map(fn (SchoolYearSubmission $submission) => $this->enrich($submission));

        return SubmissionResource::collection($submissions);
    }

    public function show(string $tenantId, string $submissionId)
    {
        $submission = SchoolYearSubmission::whereIn('school_id', TenancyDatabase::schoolIdsFor($this->sahodaya->id))
            ->with(['school', 'counts.classCategory', 'students', 'teachers', 'registration'])
            ->findOrFail($submissionId);

        return $this->ok(SubmissionResource::make($this->enrich($submission)));
    }

    private function enrich(SchoolYearSubmission $submission): SchoolYearSubmission
    {
        $submission->setAttribute('student_total', (int) $submission->counts->sum('total_count')
            ?: $submission->students->count());
        $submission->setAttribute('registration_status', $submission->registration?->registration_status);

        return $submission;
    }
}
