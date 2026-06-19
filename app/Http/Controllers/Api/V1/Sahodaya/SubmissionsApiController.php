<?php

namespace App\Http\Controllers\Api\V1\Sahodaya;

use App\Http\Resources\SubmissionResource;
use App\Models\SchoolYearSubmission;
use App\Models\SubmissionStudent;
use App\Support\TenancyDatabase;
use App\Support\TenantStorage;

class SubmissionsApiController extends SahodayaApiController
{
    public function index()
    {
        $submissions = SchoolYearSubmission::whereIn('school_id', TenancyDatabase::schoolIdsFor($this->sahodaya->id))
            ->with(['school', 'counts', 'students', 'registration.payments'])
            ->orderByDesc('academic_year')
            ->orderBy('school_id')
            ->get()
            ->map(fn (SchoolYearSubmission $submission) => $this->enrich($submission));

        return SubmissionResource::collection($submissions);
    }

    public function show(string $tenantId, string $submissionId)
    {
        $submission = SchoolYearSubmission::whereIn('school_id', TenancyDatabase::schoolIdsFor($this->sahodaya->id))
            ->with([
                'school',
                'counts.classCategory',
                'students.schoolClass',
                'teachers.teachingType',
                'registration.payments',
            ])
            ->findOrFail($submissionId);

        return $this->ok(SubmissionResource::make($this->enrich($submission)));
    }

    public function studentImage(string $tenantId, string $studentId)
    {
        $schoolIds = TenancyDatabase::schoolIdsFor($this->sahodaya->id);

        $student = SubmissionStudent::query()
            ->whereHas('submission', fn ($q) => $q->whereIn('school_id', $schoolIds))
            ->with('submission.school')
            ->findOrFail($studentId);

        abort_unless($student->image_path, 404);

        return TenantStorage::downloadResponse($student->submission->school, $student->image_path);
    }

    private function enrich(SchoolYearSubmission $submission): SchoolYearSubmission
    {
        $submission->setAttribute('student_total', (int) $submission->counts->sum('total_count')
            ?: $submission->students->count());
        $submission->setAttribute('registration_status', $submission->registration?->registration_status);

        $studentImages = $submission->relationLoaded('students')
            ? $submission->students->whereNotNull('image_path')->count()
            : 0;

        $paymentProofs = 0;
        if ($submission->relationLoaded('registration') && $submission->registration) {
            $payments = $submission->registration->relationLoaded('payments')
                ? $submission->registration->payments
                : collect();
            $paymentProofs = $payments->whereNotNull('payment_proof_path')->count();
        }

        $submission->setAttribute('student_image_count', $studentImages);
        $submission->setAttribute('payment_proof_count', $paymentProofs);
        $submission->setAttribute('submitted_file_count', $studentImages + $paymentProofs);

        $previewPaths = [];
        if ($submission->relationLoaded('registration') && $submission->registration?->relationLoaded('payments')) {
            foreach ($submission->registration->payments as $payment) {
                if ($payment->payment_proof_path) {
                    $previewPaths[] = [
                        'type' => 'payment',
                        'path' => "/api/v1/sahodaya/{$this->sahodaya->id}/payments/{$payment->id}/proof",
                    ];
                }
            }
        }
        if ($submission->relationLoaded('students')) {
            foreach ($submission->students as $student) {
                if ($student->image_path) {
                    $previewPaths[] = [
                        'type' => 'student',
                        'path' => "/api/v1/sahodaya/{$this->sahodaya->id}/submissions/submission-students/{$student->id}/image",
                    ];
                }
            }
        }
        $submission->setAttribute('preview_files', array_slice($previewPaths, 0, 4));

        return $submission;
    }
}
