<?php

namespace App\Services\Training;

use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\DB;

class TrainingWaitlistService
{
    /** Statuses that occupy a capacity seat. */
    public const SEATED_STATUSES = ['registered', 'confirmed', 'completed'];

    public function __construct(
        private readonly TrainingRegistrationLifecycle $lifecycle,
    ) {}

    public function seatedCount(TrainingProgram $program): int
    {
        return TrainingRegistration::where('program_id', $program->id)
            ->whereIn('status', self::SEATED_STATUSES)
            ->count();
    }

    public function hasOpenSeat(TrainingProgram $program): bool
    {
        if (! $program->max_participants) {
            return true;
        }

        return $this->seatedCount($program) < (int) $program->max_participants;
    }

    /**
     * Status + waitlist_position for a new registration.
     *
     * @return array{status: string, waitlist_position: ?int}
     */
    public function resolveCreateAttributes(TrainingProgram $program, ?string $source = null): array
    {
        if ($this->hasOpenSeat($program)) {
            return [
                'status' => $this->lifecycle->initialStatus($program, $source),
                'waitlist_position' => null,
            ];
        }

        return $this->enqueueAttributes($program);
    }

    /**
     * @return array{status: string, waitlist_position: int}
     */
    public function enqueueAttributes(TrainingProgram $program): array
    {
        $position = (int) TrainingRegistration::where('program_id', $program->id)
            ->where('status', 'waitlisted')
            ->max('waitlist_position');

        return [
            'status' => 'waitlisted',
            'waitlist_position' => $position + 1,
        ];
    }

    /**
     * Place an existing registration onto the waitlist (rare; prefer resolveCreateAttributes).
     */
    public function enqueue(TrainingRegistration $registration): TrainingRegistration
    {
        $registration->loadMissing('program');
        $attrs = $this->enqueueAttributes($registration->program);
        $registration->update($attrs);

        return $registration->fresh();
    }

    /**
     * Promote the first waitlisted registrant into a seat, if capacity allows.
     */
    public function promoteNext(TrainingProgram $program): ?TrainingRegistration
    {
        return DB::transaction(function () use ($program) {
            if (! $this->hasOpenSeat($program)) {
                return null;
            }

            /** @var TrainingRegistration|null $next */
            $next = TrainingRegistration::where('program_id', $program->id)
                ->where('status', 'waitlisted')
                ->orderBy('waitlist_position')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $next) {
                return null;
            }

            $status = $this->lifecycle->initialStatus($program, $next->registration_source);
            $next->update([
                'status' => $status,
                'waitlist_position' => null,
            ]);

            $this->reindexPositions($program);
            $this->notifyPromoted($next->fresh(['teacher', 'program']));

            if ($program->usesSchoolBatchFee() && $next->school_id) {
                $school = $next->school;
                if ($school) {
                    app(TrainingSchoolFeeService::class)->syncForSchool($program, $school);
                }
            }

            return $next->fresh();
        });
    }

    /**
     * Cancel/withdraw a registration and promote the next waitlisted seat-holder when a seat frees.
     */
    public function cancelAndPromote(TrainingRegistration $registration): TrainingRegistration
    {
        return DB::transaction(function () use ($registration) {
            $registration->loadMissing('program');
            $program = $registration->program;
            $wasSeated = in_array($registration->status, self::SEATED_STATUSES, true);
            $wasWaitlisted = $registration->status === 'waitlisted';

            $registration->update([
                'status' => 'cancelled',
                'waitlist_position' => null,
            ]);

            if ($wasWaitlisted && $program) {
                $this->reindexPositions($program);
            }

            if ($wasSeated && $program) {
                $this->promoteNext($program);

                if ($program->usesSchoolBatchFee() && $registration->school_id) {
                    $school = $registration->school;
                    if ($school) {
                        app(TrainingSchoolFeeService::class)->syncForSchool($program, $school);
                    }
                }
            }

            return $registration->fresh();
        });
    }

    public function reindexPositions(TrainingProgram $program): void
    {
        $rows = TrainingRegistration::where('program_id', $program->id)
            ->where('status', 'waitlisted')
            ->orderBy('waitlist_position')
            ->orderBy('id')
            ->get();

        $position = 1;
        foreach ($rows as $row) {
            if ((int) $row->waitlist_position !== $position) {
                $row->update(['waitlist_position' => $position]);
            }
            $position++;
        }
    }

    private function notifyPromoted(TrainingRegistration $registration): void
    {
        $registration->loadMissing(['teacher', 'program']);
        $teacherUser = $registration->teacher?->user_id
            ? User::find($registration->teacher->user_id)
            : null;

        if (! $teacherUser) {
            return;
        }

        app(NotificationService::class)->notifyFromTemplate(
            $teacherUser,
            'training.waitlist.promoted',
            [
                'program_title' => $registration->program?->title ?? 'Training',
                'teacher_name' => $registration->teacher?->name ?? 'Teacher',
                'status' => $registration->status,
            ]
        );
    }
}
