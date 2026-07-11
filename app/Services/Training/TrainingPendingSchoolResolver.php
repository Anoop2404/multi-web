<?php

namespace App\Services\Training;

use App\Models\Tenant;
use App\Models\TrainingPendingSchool;
use App\Models\TrainingRegistration;
use App\Services\Audit\DataChangeLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrainingPendingSchoolResolver
{
    public function link(TrainingPendingSchool $pending, Tenant $school): TrainingPendingSchool
    {
        if ($pending->status !== 'pending') {
            throw ValidationException::withMessages([
                'pending_school' => 'Only pending school requests can be linked.',
            ]);
        }

        $program = $pending->program;
        abort_unless($program, 404);

        if ($school->type !== 'school' || $school->parent_id !== $program->tenant_id) {
            throw ValidationException::withMessages([
                'school_id' => 'Select a member school belonging to this Sahodaya.',
            ]);
        }

        return DB::transaction(function () use ($pending, $school, $program) {
            $pending->update([
                'status'           => 'linked',
                'linked_school_id' => $school->id,
            ]);

            $registrations = TrainingRegistration::query()
                ->where('pending_school_id', $pending->id)
                ->with('teacher')
                ->get();

            foreach ($registrations as $registration) {
                $registration->update(['school_id' => $school->id]);

                $teacher = $registration->teacher;
                if ($teacher && $teacher->tenant_id === $program->tenant_id) {
                    $teacher->update(['tenant_id' => $school->id]);
                }
            }

            app(DataChangeLogger::class)->event(
                'pending_school_linked',
                "Pending school \"{$pending->school_name}\" linked to {$school->name}",
                $school->id,
                'training',
                $pending,
                [
                    'pending_school_id' => $pending->id,
                    'linked_school_id'  => $school->id,
                    'registrations'     => $registrations->count(),
                ],
            );

            return $pending->fresh();
        });
    }

    public function reject(TrainingPendingSchool $pending, ?string $reason = null): TrainingPendingSchool
    {
        if ($pending->status !== 'pending') {
            throw ValidationException::withMessages([
                'pending_school' => 'Only pending school requests can be rejected.',
            ]);
        }

        return DB::transaction(function () use ($pending, $reason) {
            $pending->update([
                'status'           => 'rejected',
                'linked_school_id' => null,
            ]);

            TrainingRegistration::query()
                ->where('pending_school_id', $pending->id)
                ->whereIn('status', ['registered', 'confirmed'])
                ->update(['status' => 'cancelled']);

            app(DataChangeLogger::class)->event(
                'pending_school_rejected',
                "Pending school \"{$pending->school_name}\" rejected".($reason ? ": {$reason}" : ''),
                null,
                'training',
                $pending,
                [
                    'pending_school_id' => $pending->id,
                    'reason'            => $reason,
                ],
            );

            return $pending->fresh();
        });
    }
}
