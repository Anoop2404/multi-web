<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sahodayaId = $request->route('tenantId');

        return [
            'id'                  => $this->id,
            'school_id'           => $this->school_id,
            'academic_year'       => $this->academic_year,
            'full_records_status' => $this->full_records_status,
            'counts_status'       => $this->counts_status,
            'teacher_status'      => $this->teacher_status,
            'student_total'       => $this->when(isset($this->student_total), $this->student_total),
            'registration_status' => $this->when(isset($this->registration_status), $this->registration_status),
            'student_image_count' => $this->when(isset($this->student_image_count), $this->student_image_count),
            'payment_proof_count' => $this->when(isset($this->payment_proof_count), $this->payment_proof_count),
            'submitted_file_count'=> $this->when(isset($this->submitted_file_count), $this->submitted_file_count),
            'preview_files'       => $this->when(isset($this->preview_files), $this->preview_files),
            'school'              => $this->whenLoaded('school', fn () => SchoolResource::make($this->school)),
            'school_name'         => $this->whenLoaded('school', fn () => $this->school->name),
            'students'            => $this->whenLoaded('students', fn () => $this->students->map(fn ($student) => [
                'id'         => $student->id,
                'name'       => $student->name,
                'class'      => $student->schoolClass?->name ?? $student->class,
                'section'    => $student->section,
                'has_image'  => (bool) $student->image_path,
                'image_path' => $student->image_path && $sahodayaId
                    ? "/api/v1/sahodaya/{$sahodayaId}/submissions/submission-students/{$student->id}/image"
                    : null,
            ])->values()),
            'teachers'            => $this->whenLoaded('teachers', fn () => $this->teachers->map(fn ($teacher) => [
                'id'            => $teacher->id,
                'name'          => $teacher->name,
                'subject'       => $teacher->subject,
                'teaching_type' => $teacher->relationLoaded('teachingType') && $teacher->teachingType
                    ? ['label' => $teacher->teachingType->label]
                    : null,
            ])->values()),
            'payments'            => $this->when(
                $this->relationLoaded('registration') && $this->registration?->relationLoaded('payments'),
                fn () => MembershipPaymentResource::collection($this->registration->payments),
            ),
            'created_at'          => $this->created_at?->toIso8601String(),
        ];
    }
}
