<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestItemHead;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRankPoint;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use App\Models\FestSchoolEventFee;
use App\Models\StateRemittance;
use App\Support\AcademicYear;

/**
 * Single readiness checklist for Sports events (Setup hub + Overview).
 * Event Head is the unit of fee/policy configuration; state remittance is season-level only.
 */
class FestSportsChecklist
{
    /**
     * @param  array{
     *     base?: string,
     *     headCount?: int,
     *     itemCount?: int,
     *     itemsWithHead?: int,
     *     headsWithDates?: int,
     *     headsWithFees?: int,
     *     headsFullyConfigured?: bool,
     *     itemsWithFees?: int,
     *     rankPointCount?: int,
     *     feeConfigured?: bool
     * }  $precomputed
     * @return list<array{key: string, label: string, done: bool, hint: ?string, href?: ?string, detail?: ?string, optional?: bool}>
     */
    public function forEvent(FestEvent $event, array $precomputed = []): array
    {
        $base = $precomputed['base'] ?? "/sahodaya-admin/{$event->tenant_id}/events/{$event->id}";
        $stats = array_merge($this->computeStats($event), $precomputed);

        $headCount = (int) $stats['headCount'];
        $itemCount = (int) $stats['itemCount'];
        $itemsWithHead = (int) $stats['itemsWithHead'];
        $headsWithDates = (int) $stats['headsWithDates'];
        $headsWithFees = (int) $stats['headsWithFees'];
        $headsFullyConfigured = (bool) $stats['headsFullyConfigured'];
        $itemsWithFees = (int) $stats['itemsWithFees'];
        $rankPointCount = (int) $stats['rankPointCount'];
        $feeConfigured = (bool) $stats['feeConfigured'];

        $approved = FestRegistration::where('event_id', $event->id)->where('status', 'approved')->count();
        $pending = FestRegistration::where('event_id', $event->id)->where('status', 'submitted')->count();
        $scheduleRows = FestSchedule::where('event_id', $event->id)->count();
        $participantCount = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)->where('status', 'approved'))
            ->where('participant_role', '!=', 'standby')
            ->whereNull('disqualified_at')
            ->count();
        $markedCount = FestMark::where('event_id', $event->id)
            ->whereHas('participant', fn ($q) => $q
                ->where('participant_role', '!=', 'standby')
                ->whereNull('disqualified_at'))
            ->where(function ($q) {
                $q->whereNotNull('grade')->orWhereNotNull('score')->orWhereNotNull('position');
            })
            ->count();

        $feeService = app(FestSchoolEventFeeService::class);
        $feeRequired = $feeService->feeRequired($event);
        $pendingFees = 0;
        $verifiedFees = 0;
        if ($feeRequired) {
            $schoolFees = FestSchoolEventFee::where('event_id', $event->id)->get();
            $pendingFees = $schoolFees->whereIn('status', ['submitted', 'proof_uploaded', 'uploaded', 'pending'])->count();
            $verifiedFees = $schoolFees->where('status', 'approved')->count();
        }

        $numberingSettings = is_array($event->numbering_settings) ? $event->numbering_settings : [];
        $numberingConfigured = $numberingSettings !== [];

        return [
            [
                'key'   => 'event',
                'label' => 'Event details & dates',
                'hint'  => 'Title, status, fest dates, registration open/close.',
                'href'  => "{$base}?overview=1",
                'done'  => filled($event->title) && filled($event->status),
            ],
            [
                'key'    => 'heads',
                'label'  => 'Event Heads configured',
                'hint'   => 'Heads exist and each has school/student/team fees or policy set.',
                'href'   => "{$base}/competition",
                'done'   => $headsFullyConfigured,
                'detail' => $headCount > 0
                    ? "{$headsWithFees}/{$headCount} head(s) with fees"
                    : 'No heads yet',
            ],
            [
                'key'    => 'head_windows',
                'label'  => 'Event Head schedule windows set',
                'hint'   => 'Registration and/or competition dates on each head.',
                'href'   => "{$base}/competition",
                'done'   => $headCount > 0 && $headsWithDates === $headCount,
                'detail' => $headCount > 0 ? "{$headsWithDates}/{$headCount} with dates" : null,
            ],
            [
                'key'    => 'items',
                'label'  => 'Items added',
                'hint'   => 'Enabled items linked under Event Heads.',
                'href'   => "{$base}/items",
                'done'   => $itemCount > 0 && $itemsWithHead === $itemCount,
                'detail' => $itemCount > 0 ? "{$itemsWithHead}/{$itemCount} linked to a head" : null,
            ],
            [
                'key'      => 'item_fees',
                'label'    => 'Item overrides (optional)',
                'hint'     => 'Per-item fee overrides — never required; defaults come from each Event Head.',
                'href'     => "{$base}/competition",
                'done'     => $itemsWithFees > 0,
                'optional' => true,
                'detail'   => $itemsWithFees > 0 ? "{$itemsWithFees} item(s) with custom fee" : 'Using head defaults',
            ],
            [
                'key'      => 'fees',
                'label'    => 'Event-wide fee override (optional)',
                'hint'     => 'Optional fallback or fee cap across heads. Composite billing is always on for Sports.',
                'href'     => "{$base}/settings/fees",
                'done'     => $feeConfigured,
                'optional' => true,
                'detail'   => $feeConfigured ? 'Fallback configured' : 'Using per-head fees only',
            ],
            [
                'key'   => 'rank_points',
                'label' => 'Rank points set',
                'hint'  => 'Fixed team points per rank (1st, 2nd…).',
                'href'  => "{$base}/settings/points",
                'done'  => $rankPointCount > 0,
            ],
            [
                'key'   => 'registration',
                'label' => 'Registration window set',
                'hint'  => 'Event-level or per-head registration open/close.',
                'href'  => "{$base}/settings/registration",
                'done'  => filled($event->event_reg_start) || filled($event->event_reg_end) || $headsWithDates > 0,
            ],
            [
                'key'   => 'numbering',
                'label' => 'Numbering configured',
                'hint'  => 'Save chest / event-reg ranges and auto-assign under Settings → Chest numbering.',
                'href'  => "{$base}/settings/numbering",
                'done'  => $numberingConfigured,
                'detail' => $numberingConfigured ? 'Custom numbering saved' : 'Using platform defaults until saved',
            ],
            [
                'key'   => 'registrations',
                'label' => 'Registration opened & entries reviewed',
                'hint'  => $pending > 0
                    ? "{$pending} pending approval"
                    : ($approved === 0 ? 'Set status to Registration open when ready for schools' : null),
                'href'  => "{$base}/registrations",
                'done'  => in_array($event->status, ['registration_open', 'ongoing', 'completed'], true)
                    && $pending === 0
                    && $approved > 0,
            ],
            [
                'key'   => 'school_fees',
                'label' => 'School fest fees verified',
                'hint'  => ! $feeRequired
                    ? 'No fest fees for this round'
                    : ($pendingFees > 0 ? "{$pendingFees} payment(s) awaiting verification" : null),
                'href'  => "{$base}/fees",
                'done'  => ! $feeRequired || ($pendingFees === 0 && $verifiedFees > 0),
            ],
            [
                'key'   => 'schedule',
                'label' => 'Schedule built & published',
                'hint'  => $scheduleRows === 0 ? 'Generate or enter schedule rows' : "{$scheduleRows} slots",
                'href'  => "{$base}/schedule",
                'done'  => $scheduleRows > 0 && (bool) $event->schedule_published,
            ],
            [
                'key'   => 'marks',
                'label' => 'Marks entry complete',
                'hint'  => $participantCount > 0 ? "{$markedCount}/{$participantCount} marked" : 'No participants yet',
                'href'  => "{$base}/marks",
                'done'  => $participantCount > 0 && $markedCount >= $participantCount,
            ],
            [
                'key'   => 'published',
                'label' => 'Results published',
                'hint'  => 'Schools see rankings when results are published.',
                'href'  => "{$base}/results",
                'done'  => (bool) $event->results_published,
            ],
        ];
    }

    /**
     * Setup hub alias — same checklist shape.
     *
     * @param  array<string, mixed>  $precomputed
     * @return list<array{key: string, label: string, done: bool, hint: ?string, href?: ?string, detail?: ?string, optional?: bool}>
     */
    public function forSetupHub(FestEvent $event, array $precomputed = []): array
    {
        return $this->forEvent($event, $precomputed);
    }

    /**
     * Sahodaya/year remittance note for the Sports program hub (not a per-event step).
     *
     * @return array{show: bool, done: bool, label: string, hint: ?string}
     */
    public function seasonRemittanceBanner(string $sahodayaId, ?string $academicYearLabel = null): array
    {
        $yearLabel = $academicYearLabel ?? AcademicYear::forSahodaya($sahodayaId);

        $done = StateRemittance::where('sahodaya_id', $sahodayaId)
            ->where('status', 'verified')
            ->when($yearLabel, fn ($q) => $q->where('academic_year', $yearLabel))
            ->exists();

        return [
            'show'  => true,
            'done'  => $done,
            'label' => 'State remittance (season)',
            'hint'  => $done
                ? 'Verified for this academic year.'
                : 'Upload and verify under Tools → State remittances. This is a Sahodaya-year task, not per discipline event.',
        ];
    }

    /** @return array<string, mixed> */
    private function computeStats(FestEvent $event): array
    {
        $headCount = FestItemHead::where('event_id', $event->id)->count();
        $itemCount = FestEventItem::where('event_id', $event->id)->where('is_enabled', true)->count();
        $itemsWithHead = FestEventItem::where('event_id', $event->id)->where('is_enabled', true)->whereNotNull('head_id')->count();
        $headsWithDates = FestItemHead::where('event_id', $event->id)
            ->where(function ($q) {
                $q->whereNotNull('reg_start')->orWhereNotNull('competition_start');
            })
            ->count();
        $headsWithFees = FestItemHead::where('event_id', $event->id)
            ->where(function ($q) {
                $q->whereNotNull('school_registration_fee')
                    ->orWhereNotNull('student_registration_fee')
                    ->orWhereNotNull('team_registration_fee')
                    ->orWhereNotNull('default_item_fee')
                    ->orWhereNotNull('extra_item_fee');
            })
            ->count();

        $feeService = app(FestSchoolEventFeeService::class);
        $schedule = $feeService->resolveSchedule($event);
        $feeModel = $schedule['fee_model'] ?? $event->fee_settings['fee_model'] ?? null;
        $storedFees = is_array($event->fee_settings) ? $event->fee_settings : [];

        // Sports: fee_model is always sports_composite — only count real event-wide overrides.
        $feeConfigured = $event->event_type === 'sports'
            ? collect(['school_fee_cap', 'school_registration_fee', 'student_registration_fee', 'team_registration_fee', 'default_item_fee', 'extra_item_fee'])
                ->contains(fn (string $key) => filled($storedFees[$key] ?? null) || filled($schedule[$key] ?? null))
            : ($feeModel && $feeModel !== 'none');

        return [
            'headCount' => $headCount,
            'itemCount' => $itemCount,
            'itemsWithHead' => $itemsWithHead,
            'headsWithDates' => $headsWithDates,
            'headsWithFees' => $headsWithFees,
            'headsFullyConfigured' => $headCount > 0 && $headsWithFees === $headCount,
            'itemsWithFees' => FestEventItem::where('event_id', $event->id)->where('is_enabled', true)->whereNotNull('fee_amount')->count(),
            'rankPointCount' => FestRankPoint::where('event_id', $event->id)->count(),
            'feeConfigured' => $feeConfigured,
        ];
    }
}
