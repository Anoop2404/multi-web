<?php

namespace App\Services\Training;

use App\Models\Tenant;
use App\Models\TrainingPendingSchool;
use App\Models\TrainingRegistration;
use App\Models\User;
use App\Services\Audit\DataChangeLogger;
use App\Services\Notifications\NotificationService;
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
            /** @var TrainingPendingSchool $locked */
            $locked = TrainingPendingSchool::query()->whereKey($pending->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'pending_school' => 'Only pending school requests can be linked.',
                ]);
            }

            $locked->update([
                'status'           => 'linked',
                'linked_school_id' => $school->id,
            ]);

            $registrations = TrainingRegistration::query()
                ->where('pending_school_id', $locked->id)
                ->with('teacher')
                ->lockForUpdate()
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
                "Pending school \"{$locked->school_name}\" linked to {$school->name}",
                $school->id,
                'training',
                $locked,
                [
                    'pending_school_id' => $locked->id,
                    'linked_school_id'  => $school->id,
                    'registrations'     => $registrations->count(),
                ],
            );

            $this->notifyTeachers(
                $locked->fresh(['program']),
                'training.pending_school.linked',
                [
                    'pending_school_name' => $locked->school_name,
                    'school_name'         => $school->name,
                    'program_title'       => $program->title,
                    'reason'              => '',
                ],
                "/portal/teacher/{$school->id}",
            );

            return $locked->fresh();
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
            /** @var TrainingPendingSchool $locked */
            $locked = TrainingPendingSchool::query()->whereKey($pending->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'pending_school' => 'Only pending school requests can be rejected.',
                ]);
            }

            $locked->loadMissing('program');
            $locked->update([
                'status'           => 'rejected',
                'linked_school_id' => null,
            ]);

            TrainingRegistration::query()
                ->where('pending_school_id', $locked->id)
                ->whereIn('status', ['registered', 'confirmed'])
                ->update(['status' => 'cancelled']);

            app(DataChangeLogger::class)->event(
                'pending_school_rejected',
                "Pending school \"{$locked->school_name}\" rejected".($reason ? ": {$reason}" : ''),
                $locked->program?->tenant_id,
                'training',
                $locked,
                [
                    'pending_school_id' => $locked->id,
                    'reason'            => $reason,
                ],
            );

            $this->notifyTeachers(
                $locked,
                'training.pending_school.rejected',
                [
                    'pending_school_name' => $locked->school_name,
                    'school_name'         => $locked->school_name,
                    'program_title'       => $locked->program?->title ?? 'Training program',
                    'reason'              => $reason ?? 'Contact your Sahodaya for details.',
                ],
                null,
            );

            return $locked->fresh();
        });
    }

    /** @param  array<string, string>  $replacements */
    private function notifyTeachers(TrainingPendingSchool $pending, string $slug, array $replacements, ?string $actionUrl): void
    {
        $service = app(NotificationService::class);
        $userIds = TrainingRegistration::query()
            ->where('pending_school_id', $pending->id)
            ->with('teacher:id,user_id')
            ->get()
            ->pluck('teacher.user_id')
            ->filter()
            ->unique();

        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if ($user) {
                $service->notifyFromTemplate($user, $slug, $replacements, $actionUrl);
            }
        }
    }
}
