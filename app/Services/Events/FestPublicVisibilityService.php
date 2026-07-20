<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestSchedule;
use App\Models\Tenant;

/**
 * Real-world public visibility rules for festival portals.
 *
 * School/cluster fests: names on schedule are normal.
 * District/state Kalolsavam on-stage: chest-only until results; stage-entry reveal respected.
 * Off-stage: level registration number as public identifier.
 * Sports: names and heat results visible during the event.
 */
class FestPublicVisibilityService
{
    public function __construct(
        private FestChestNumberService $chestNumbers,
    ) {}

    public function isSportsEvent(FestEvent $event): bool
    {
        return $event->event_type === 'sports';
    }

    public function isOnStage(FestParticipant $participant): bool
    {
        return ($participant->registration?->item?->stage_type ?? '') === 'on_stage';
    }

    public function isOffStage(FestParticipant $participant): bool
    {
        return ($participant->registration?->item?->stage_type ?? '') === 'off_stage';
    }

    /** District-level and above Kalolsavam uses chest anonymity before results. */
    public function strictAnonymity(FestEvent $event): bool
    {
        if ($this->isSportsEvent($event)) {
            return false;
        }

        $level = $event->level_round ?? 'sahodaya';

        return in_array($level, ['cluster', 'subdistrict', 'district', 'state', 'sahodaya'], true);
    }

    public function showParticipantName(FestEvent $event, FestParticipant $participant): bool
    {
        if ($event->results_published) {
            return true;
        }

        if ($this->isSportsEvent($event)) {
            return true;
        }

        if (! $this->strictAnonymity($event)) {
            return true;
        }

        return false;
    }

    public function showSchoolName(FestEvent $event): bool
    {
        return (bool) $event->results_published;
    }

    public function showIndividualMarks(FestEvent $event): bool
    {
        if ($event->results_published) {
            return true;
        }

        return $this->isSportsEvent($event);
    }

    public function allowNameSearch(FestEvent $event): bool
    {
        if ($event->results_published) {
            return true;
        }

        if ($this->isSportsEvent($event)) {
            return true;
        }

        return ! $this->strictAnonymity($event);
    }

    public function searchPlaceholder(FestEvent $event): string
    {
        if ($this->allowNameSearch($event)) {
            return 'Chest number, level reg no, or name';
        }

        return 'Chest number or level reg no (e.g. D-0042)';
    }

    public function publicReference(FestEvent $event, FestParticipant $participant): string
    {
        if ($this->isOffStage($participant) && ! $this->isSportsEvent($event)) {
            return $participant->level_registration_number ?? '—';
        }

        $label = $this->chestNumbers->participantLabel($participant);

        return $label !== '—' ? $label : ($participant->level_registration_number ?? '—');
    }

    public function participantLinkRef(FestParticipant $participant): ?string
    {
        if ($participant->chest_no) {
            return (string) $participant->chest_no;
        }

        return $participant->level_registration_number;
    }

    /** @return array<string, mixed> */
    public function formatPublicParticipant(
        FestEvent $event,
        FestParticipant $participant,
        ?FestSchedule $schedule = null,
        ?FestMark $mark = null,
    ): array {
        $showMarks = $this->showIndividualMarks($event);
        $showName = $this->showParticipantName($event, $participant);

        return [
            'reference'          => $this->publicReference($event, $participant),
            'link_ref'           => $this->participantLinkRef($participant),
            'name'               => $showName ? ($participant->student?->name ?? $participant->teacher?->name) : null,
            'item_title'         => $participant->registration?->item?->title,
            'team_name'          => $showName ? $participant->group?->team_name : null,
            'scheduled_at'       => $schedule?->scheduled_at,
            'stage'              => $schedule?->stage,
            'sort_order'         => $schedule?->sort_order,
            'position'           => $showMarks ? $mark?->position : null,
            'grade'              => $showMarks ? $mark?->grade : null,
            'score'              => $showMarks ? $mark?->score : null,
            'measurement_value'  => $showMarks ? $mark?->measurement_value : null,
            'measurement_unit'   => $showMarks ? $mark?->measurement_unit : null,
            'disqualified'       => (bool) $participant->disqualified_at,
            'show_name'          => $showName,
            'show_marks'         => $showMarks,
        ];
    }

    public function isPublicAudience(string $audience): bool
    {
        return $audience === 'public';
    }

    public function showSchedulePublicly(FestEvent $event): bool
    {
        return (bool) $event->schedule_published;
    }

    /** @return array{reference: string, name: ?string, school: ?string, order: ?int} */
    public function formatReportRow(FestEvent $event, FestParticipant $participant, string $audience = 'staff', ?FestSchedule $schedule = null): array
    {
        $public = $this->isPublicAudience($audience);
        $showName = ! $public || $this->showParticipantName($event, $participant);
        $showSchool = ! $public || $this->showSchoolName($event);

        return [
            'reference' => $this->publicReference($event, $participant),
            'name'      => $showName ? ($participant->student?->name ?? $participant->teacher?->name) : null,
            'school'    => $showSchool ? ($participant->registration?->school?->name ?? Tenant::find($participant->registration?->school_id)?->name) : null,
            'order'     => $schedule?->sort_order,
            'item'      => $participant->registration?->item?->title,
        ];
    }

    public function findParticipantByRef(FestEvent $event, string $ref): ?FestParticipant
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }

        return FestParticipant::whereHas('registration', fn ($r) => $r
            ->where('event_id', $event->id)
            ->where('status', 'approved'))
            ->with(['student', 'teacher', 'registration.item', 'registration.event', 'group'])
            ->where(function ($q) use ($ref) {
                if (ctype_digit($ref)) {
                    $q->where('chest_no', (int) $ref);
                } else {
                    $q->where('level_registration_number', $ref);
                }
            })
            ->first();
    }
}
