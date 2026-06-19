<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentDueItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $item = is_array($this->resource) ? $this->resource : $this->resource->toArray();

        return [
            'id'                    => $item['id'] ?? null,
            'school_id'             => $item['school_id'],
            'academic_year'         => $item['academic_year'],
            'reg_no'                => $item['reg_no'] ?? null,
            'registration_status'   => $item['registration_status'],
            'membership_fee_amount' => $item['membership_fee_amount'],
            'source'                => $item['source'] ?? 'registration',
            'school'                => $item['school'] ?? null,
            'updated_at'            => $item['updated_at'] ?? null,
        ];
    }
}
