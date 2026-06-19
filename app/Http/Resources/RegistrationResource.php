<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'school_id'            => $this->school_id,
            'academic_year'        => $this->academic_year,
            'reg_no'               => $this->reg_no,
            'registration_status'  => $this->registration_status,
            'membership_fee_amount'=> $this->membership_fee_amount,
            'submission'           => $this->whenLoaded('submission', fn () => [
                'id'                  => $this->submission->id,
                'full_records_status' => $this->submission->full_records_status,
                'counts_status'       => $this->submission->counts_status,
                'teacher_status'      => $this->submission->teacher_status,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
