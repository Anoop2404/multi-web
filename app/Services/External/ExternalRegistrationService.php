<?php

namespace App\Services\External;

use App\Models\ExternalSahodaya;
use App\Models\ExternalSchool;

class ExternalRegistrationService
{
    /**
     * Submit an external school item registration.
     *
     * @return array<string, mixed>
     */
    public function createRegistration(ExternalSchool $school, string $itemId, array $participants): array
    {
        return [
            'id'                 => (string) \Illuminate\Support\Str::uuid(),
            'external_school_id' => $school->id,
            'item_id'            => $itemId,
            'status'             => 'submitted',
            'participants'       => $participants,
            'created_at'         => now()->toIso8601String(),
        ];
    }

    /**
     * Upload offline payment proof for external school registration fee.
     *
     * @return array<string, mixed>
     */
    public function submitPaymentProof(ExternalSchool $school, float $amount, string $utrReference, string $proofFilePath): array
    {
        return [
            'id'                 => (string) \Illuminate\Support\Str::uuid(),
            'external_school_id' => $school->id,
            'amount'             => $amount,
            'utr_reference'      => $utrReference,
            'proof_file'         => $proofFilePath,
            'status'             => 'pending_verification',
            'submitted_at'       => now()->toIso8601String(),
        ];
    }
}
