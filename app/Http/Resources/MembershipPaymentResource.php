<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'school_id'          => $this->school_id,
            'academic_year'      => $this->academic_year,
            'amount'             => $this->amount,
            'status'             => $this->status,
            'payment_method'     => $this->payment_method,
            'transaction_ref'    => $this->transaction_ref,
            'rejection_reason'   => $this->rejection_reason,
            'proof_url'          => $this->proof_url,
            'school'             => $this->whenLoaded('school', fn () => [
                'id'            => $this->school->id,
                'name'          => $this->school->name,
                'school_prefix' => $this->school->school_prefix,
            ]),
            'created_at'  => $this->created_at?->toIso8601String(),
            'verified_at' => $this->verified_at?->toIso8601String(),
        ];
    }
}
