<?php

namespace App\Services\Training;

use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;

class TrainingRegistrationLifecycle
{
    /**
     * Initial status after nomination / QR / self-register.
     *
     * QR: confirm immediately so teachers can attend; venue fee collection /
     * payment approval happens later on Fee approvals.
     * Fee-free: confirm immediately.
     * School/portal paid nominations: stay registered until fee is approved.
     */
    public function initialStatus(TrainingProgram $program, ?string $source = null): string
    {
        if ($source === 'qr' || ! $program->hasFee()) {
            return 'confirmed';
        }

        return 'registered';
    }

    public function canMarkAttendance(TrainingRegistration $registration, ?TrainingProgram $program = null): bool
    {
        $program ??= $registration->program;

        if (in_array($registration->status, ['confirmed', 'completed'], true)) {
            return true;
        }

        // QR (and fee-free leftovers) may attend even if still "registered".
        if ($registration->status === 'registered') {
            if ($registration->registration_source === 'qr') {
                return true;
            }

            return $program && ! $program->hasFee();
        }

        return false;
    }
}
