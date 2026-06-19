<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'tenant_id'        => $this->tenant_id,
            'school_class_id'  => $this->school_class_id,
            'admission_number' => $this->admission_number,
            'roll_number'      => $this->roll_number,
            'name'             => $this->name,
            'dob'              => $this->dob?->format('Y-m-d'),
            'gender'           => $this->gender,
            'blood_group'      => $this->blood_group,
            'parent_name'      => $this->parent_name,
            'parent_phone'     => $this->parent_phone,
            'parent_email'     => $this->parent_email,
            'address'          => $this->address,
            'admission_date'   => $this->admission_date?->format('Y-m-d'),
            'status'           => $this->status,
            'notes'            => $this->notes,
            'photo_url'        => $this->photoUrl(),
            'school_class'     => $this->whenLoaded('schoolClass', fn () => [
                'id'   => $this->schoolClass->id,
                'name' => $this->schoolClass->name,
                'class_category' => $this->schoolClass->relationLoaded('classCategory') && $this->schoolClass->classCategory
                    ? [
                        'id'    => $this->schoolClass->classCategory->id,
                        'label' => $this->schoolClass->classCategory->label,
                        'code'  => $this->schoolClass->classCategory->code,
                    ]
                    : null,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
