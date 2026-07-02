<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use App\Models\FestSchoolEventFee;
use App\Models\StateRemittance;

class FestLifecycleService
{
    public function __construct(public FestEvent $event) {}

    public static function for(FestEvent $event): self
    {
        return new self($event);
    }

    /** @return list<array{key: string, label: string, done: bool, hint: ?string}> */
    public function checklist(): array
    {
        $e = $this->event;

        $approved = FestRegistration::where('event_id', $e->id)->where('status', 'approved')->count();
        $pending = FestRegistration::where('event_id', $e->id)->where('status', 'submitted')->count();
        $scheduleRows = FestSchedule::where('event_id', $e->id)->count();
        $participantCount = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $e->id)->where('status', 'approved'))
            ->where('participant_role', '!=', 'standby')
            ->whereNull('disqualified_at')
            ->count();
        $markedCount = FestMark::where('event_id', $e->id)
            ->whereHas('participant', fn ($q) => $q
                ->where('participant_role', '!=', 'standby')
                ->whereNull('disqualified_at'))
            ->where(function ($q) {
                $q->whereNotNull('grade')->orWhereNotNull('score')->orWhereNotNull('position');
            })
            ->count();

        $feeService = app(FestSchoolEventFeeService::class);
        $feeRequired = $feeService->feeRequired($e);
        $pendingFees = 0;
        $verifiedFees = 0;
        if ($feeRequired) {
            $schoolFees = FestSchoolEventFee::where('event_id', $e->id)->get();
            $pendingFees = $schoolFees->whereIn('status', ['submitted', 'proof_uploaded', 'uploaded', 'pending'])->count();
            $verifiedFees = $schoolFees->where('status', 'approved')->count();
        }

        $remittanceDone = true;
        $remittanceHint = null;
        if ($e->level_round === 'sahodaya' && $e->state_program_id) {
            $activeYear = \App\Support\AcademicYear::forSahodaya($e->tenant_id);
            $yearLabel = $activeYear?->label ?? $e->academicYear?->label;
            $remittanceDone = StateRemittance::where('sahodaya_id', $e->tenant_id)
                ->where('status', 'verified')
                ->when($yearLabel, fn ($q) => $q->where('academic_year', $yearLabel))
                ->exists();
            $remittanceHint = $remittanceDone
                ? null
                : 'Upload and verify state remittance under Tools → State remittances';
        }

        return [
            [
                'key'   => 'items',
                'label' => 'Event items configured',
                'done'  => $e->items()->exists(),
                'hint'  => 'Add competition items to the catalog.',
            ],
            [
                'key'   => 'registrations',
                'label' => 'Registrations reviewed',
                'done'  => $pending === 0 && $approved > 0,
                'hint'  => $pending > 0 ? "{$pending} pending approval" : ($approved === 0 ? 'No approved registrations' : null),
            ],
            [
                'key'   => 'school_fees',
                'label' => 'School fest fees verified',
                'done'  => ! $feeRequired || ($pendingFees === 0 && $verifiedFees > 0),
                'hint'  => ! $feeRequired
                    ? 'No fest fees for this round'
                    : ($pendingFees > 0 ? "{$pendingFees} payment(s) awaiting verification" : null),
            ],
            [
                'key'   => 'state_remittance',
                'label' => 'State remittance submitted & verified',
                'done'  => $remittanceDone,
                'hint'  => $remittanceHint,
            ],
            [
                'key'   => 'schedule',
                'label' => 'Performance schedule built',
                'done'  => $scheduleRows > 0,
                'hint'  => $scheduleRows === 0 ? 'Generate or enter schedule rows' : "{$scheduleRows} slots",
            ],
            [
                'key'   => 'schedule_published',
                'label' => 'Schedule published to public',
                'done'  => (bool) $e->schedule_published,
                'hint'  => 'Publish when ready for fest day',
            ],
            [
                'key'   => 'ongoing',
                'label' => 'Event status set to Ongoing',
                'done'  => in_array($e->status, ['ongoing', 'completed'], true),
                'hint'  => 'Set status when fest days begin',
            ],
            [
                'key'   => 'marks',
                'label' => 'Mark entry complete',
                'done'  => $participantCount > 0 && $markedCount >= $participantCount,
                'hint'  => $participantCount > 0 ? "{$markedCount}/{$participantCount} marked" : 'No participants',
            ],
            [
                'key'   => 'published',
                'label' => 'Results published',
                'done'  => (bool) $e->results_published,
                'hint'  => null,
            ],
        ];
    }

    public function suggestedStatus(): ?string
    {
        $e = $this->event;

        if ($e->results_published) {
            return 'completed';
        }

        if ($e->schedule_published && in_array($e->status, ['published', 'registration_open'], true)) {
            return 'ongoing';
        }

        if ($e->isRegistrationOpen() && $e->status === 'published') {
            return 'registration_open';
        }

        return null;
    }
}
