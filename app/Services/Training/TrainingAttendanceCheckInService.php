<?php

namespace App\Services\Training;

use App\Models\TrainingAttendance;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\TrainingSession;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Validation\ValidationException;

class TrainingAttendanceCheckInService
{
    public function __construct(
        private readonly PlatformAuditLogger $audit,
    ) {}

    public function checkIn(
        TrainingProgram $program,
        TrainingSession $session,
        string $lookup,
    ): TrainingAttendance {
        abort_if($session->program_id !== $program->id, 404);

        $registration = $this->resolveRegistration($program, $lookup);

        if (! app(TrainingRegistrationLifecycle::class)->canMarkAttendance($registration, $program)) {
            throw ValidationException::withMessages([
                'lookup' => 'Only confirmed registrations can mark attendance. Current status: '.$registration->status.'.',
            ]);
        }

        $attendance = TrainingAttendance::updateOrCreate(
            [
                'session_id'      => $session->id,
                'registration_id' => $registration->id,
            ],
            [
                'status'    => 'present',
                'marked_at' => now(),
            ]
        );

        $this->audit->training(
            $program,
            'training.qr.attendance',
            "QR attendance: {$registration->teacher?->name} · {$session->title}",
            [
                'session_id'      => $session->id,
                'registration_id' => $registration->id,
                'attendance_id'   => $attendance->id,
            ],
            $attendance,
        );

        return $attendance;
    }

    private function resolveRegistration(TrainingProgram $program, string $lookup): TrainingRegistration
    {
        $lookup = trim($lookup);
        if ($lookup === '') {
            throw ValidationException::withMessages([
                'lookup' => 'Enter your registered email, mobile, or registration ID.',
            ]);
        }

        if (ctype_digit($lookup)) {
            $byId = TrainingRegistration::where('program_id', $program->id)
                ->where('id', (int) $lookup)
                ->with('teacher')
                ->first();
            if ($byId) {
                return $byId;
            }
        }

        $email = strtolower($lookup);
        $mobile = preg_replace('/\D+/', '', $lookup);

        $registration = TrainingRegistration::where('program_id', $program->id)
            ->whereHas('teacher', function ($q) use ($email, $mobile) {
                $q->whereRaw('LOWER(email) = ?', [$email]);
                if ($mobile !== '' && strlen($mobile) >= 8) {
                    $q->orWhere('mobile', $mobile);
                }
            })
            ->with('teacher')
            ->first();

        if (! $registration) {
            throw ValidationException::withMessages([
                'lookup' => 'No registration found for this programme with that email, mobile, or ID.',
            ]);
        }

        return $registration;
    }
}
