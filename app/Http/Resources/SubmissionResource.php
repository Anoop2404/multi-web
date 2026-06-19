<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'school_id'           => $this->school_id,
            'academic_year'       => $this->academic_year,
            'full_records_status' => $this->full_records_status,
            'counts_status'       => $this->counts_status,
            'teacher_status'      => $this->teacher_status,
            'student_total'       => $this->when(isset($this->student_total), $this->student_total),
            'registration_status' => $this->when(isset($this->registration_status), $this->registration_status),
            'school'              => $this->whenLoaded('school', fn () => SchoolResource::make($this->school)),
            'created_at'          => $this->created_at?->toIso8601String(),
        ];
    }
}
