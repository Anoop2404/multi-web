<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchoolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'school_prefix'     => $this->school_prefix,
            'membership_status' => $this->membership_status,
            'is_active'         => $this->is_active,
            'created_at'        => $this->created_at?->toIso8601String(),
            'student_count'         => $this->when(isset($this->student_count), $this->student_count),
            'classes_count'         => $this->when(isset($this->classes_count), $this->classes_count),
            'payment_status'        => $this->when(isset($this->payment_status), $this->payment_status),
            'payment_status_label'  => $this->when(isset($this->payment_status_label), $this->payment_status_label),
            'payment_amount'        => $this->when(isset($this->payment_amount), $this->payment_amount),
        ];
    }
}
