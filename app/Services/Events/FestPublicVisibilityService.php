<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestSchedule;
use App\Models\Tenant;
use App\Support\FestItemCategoryLabel;

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
        private FestGradePointService $gradePoints,
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

    public function showParticipantName(FestEvent $event, FestParticipant $participant, ?FestEventItem $item = null, bool $isAdminPreview = false): bool
    {
        if ($isAdminPreview) {
            return true;
        }

        if ($this->isSportsEvent($event)) {
            return true;
        }

        if (! $this->strictAnonymity($event)) {
            return true;
        }

        // Under strict anonymity, a name only reveals once the event overall has been
        // published AND — when the specific item is known — that item has also been
        // individually published. An item's own early-publish workflow only ever
        // reveals something once the event-wide toggle also allows public visibility;
        // it is never a bypass of it (an admin turning the event-wide toggle off must
        // hide everything, full stop, regardless of any item's own state). An item
        // explicitly unpublished/hidden via results_hidden always wins regardless.
        if (! $event->results_published) {
            return false;
        }

        if (! $item) {
            return true;
        }

        if ($item->results_hidden) {
            return false;
        }

        return (bool) $item->results_published_at;
    }

    public function showSchoolName(FestEvent $event, bool $isAdminPreview = false): bool
    {
        if ($isAdminPreview) {
            return true;
        }

        return (bool) $event->results_published;
    }

    /**
     * $item lines this up with showParticipantName()/isItemVisible(): the event-wide
     * flag is a hard requirement, not a fallback — an item can only reveal a mark once
     * the event overall is published AND (when the item is known) that item has also
     * been individually published, never independently of the event-wide toggle.
     */
    public function showIndividualMarks(FestEvent $event, bool $isAdminPreview = false, ?FestEventItem $item = null): bool
    {
        if ($isAdminPreview) {
            return true;
        }

        if ($this->isSportsEvent($event)) {
            return true;
        }

        if (! $event->results_published) {
            return false;
        }

        if (! $item) {
            return true;
        }

        if ($item->results_hidden) {
            return false;
        }

        return (bool) $item->results_published_at;
    }

    public function allowNameSearch(FestEvent $event, bool $isAdminPreview = false): bool
    {
        if ($isAdminPreview) {
            return true;
        }

        if ($event->results_published) {
            return true;
        }

        if ($this->isSportsEvent($event)) {
            return true;
        }

        return ! $this->strictAnonymity($event);
    }

    public function searchPlaceholder(FestEvent $event, bool $isAdminPreview = false): string
    {
        if ($this->allowNameSearch($event, $isAdminPreview)) {
            return 'Chest number, level reg no, or name';
        }

        return 'Chest number or level reg no (e.g. D-0042)';
    }

    public function publicReference(FestEvent $event, FestParticipant $participant, bool $isAdminPreview = false): string
    {
        if (! $event->results_published && ! $isAdminPreview) {
            return '—';
        }

        if ($this->isOffStage($participant) && ! $this->isSportsEvent($event)) {
            return $participant->level_registration_number ?? '—';
        }

        $label = $this->chestNumbers->participantLabel($participant);

        return $label !== '—' ? $label : ($participant->level_registration_number ?? '—');
    }

    public function participantLinkRef(FestParticipant $participant): ?string
    {
        // Keep the URL lookup unambiguous. A bare numeric level-registration number can
        // also be a different participant's chest number; the legacy resolver checks
        // chest numbers first, so links generated from that bare value could open the
        // wrong student's page. New links carry the participant row's explicit type.
        return $participant->exists ? 'p-'.$participant->getKey() : null;
    }

    /** @return array<string, mixed> */
    public function formatPublicParticipant(
        FestEvent $event,
        FestParticipant $participant,
        ?FestSchedule $schedule = null,
        ?FestMark $mark = null,
        bool $isAdminPreview = false,
    ): array {
        $item = $participant->registration?->item;
        $showMarks = $this->showIndividualMarks($event, $isAdminPreview, $item);
        $showName = $this->showParticipantName($event, $participant, $item, $isAdminPreview);

        $classGroupLabels = \App\Support\FestClassGroupScheme::labels(null, $event->rootEvent());
        $categoryLabel = FestItemCategoryLabel::resolve($item, $classGroupLabels, config('fest_item_taxonomy.arts_category', []));

        return [
            'reference'          => $this->publicReference($event, $participant, $isAdminPreview),
            'link_ref'           => $this->participantLinkRef($participant),
            'name'               => $showName ? ($participant->student?->name ?? $participant->teacher?->name) : null,
            // Gated on the same $showName check as the name itself — a photo or school
            // identifies a specific competitor just as directly as their name would.
            'photo'              => $showName ? ($participant->student?->publicPhotoUrl() ?? $participant->teacher?->publicPhotoUrl()) : null,
            'photo_fallback'     => $showName ? ($participant->student?->publicPhotoFallbackUrl() ?? $participant->teacher?->publicPhotoFallbackUrl()) : null,
            'school'             => $showName ? $participant->registration?->school?->name : null,
            'item_title'         => $item?->title,
            'category_label'     => $categoryLabel,
            'gender_label'       => \App\Support\FestSportsAgeGroup::genderLabel($item?->gender),
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

    /**
     * Every item (within this same event/region) the student or teacher behind
     * $participant is registered for — so the public participant page can show
     * a competitor's full event footprint instead of just the one item their
     * search match happened to land on.
     *
     * @return list<array<string, mixed>>
     */
    /**
     * $acrossPhases: when true and $event's hub uses phases, this student/teacher's
     * items are pulled from EVERY phase leaf under the hub, not just $event — the
     * public participant page (and the individual championship's eye-icon link into
     * it, since that leaderboard is itself combined across phases) should show a
     * student's full participation across the whole fest, not just whichever one
     * phase happened to be in the URL. Each entry is visibility-checked against ITS
     * OWN leaf event (a phase can be published independently of its siblings), not
     * against $event.
     */
    public function publicParticipantItems(FestEvent $event, FestParticipant $participant, bool $isAdminPreview = false, bool $acrossPhases = false): array
    {
        $eventsById = collect([$event->id => $event]);
        // fest_participants.event_id is sometimes null on older/imported rows even
        // though the row is very much real — its registration's event_id is the
        // authoritative source and always set (that's how $participant was resolved
        // in the first place, via findParticipantByRef()'s registration join).
        $eventIds = [$participant->registration?->event_id ?? $participant->event_id ?? $event->id];

        if ($acrossPhases) {
            $hub = $event->rootEvent();
            if ($hub->usesPhasedRegionalBilling()) {
                $phaseIds = \App\Models\FestEventPhase::where('event_id', $hub->id)->pluck('id');
                $leaves = \App\Models\FestEvent::where('parent_event_id', $hub->id)
                    ->whereIn('source_phase_id', $phaseIds)
                    ->get()
                    ->keyBy('id');
                if ($leaves->isNotEmpty()) {
                    $eventsById = $leaves;
                    $eventIds = $leaves->keys()->all();
                }
            }
        }

        // Matched through the registration's event_id, not the participant row's own
        // event_id column — that column is null on some rows even though the
        // participant is real and correctly tied to an event via its registration
        // (same authoritative source findParticipantByRef() itself joins through).
        $entries = FestParticipant::whereHas('registration', fn ($q) => $q->whereIn('event_id', $eventIds))
            ->where('participant_role', '!=', 'standby')
            ->when($participant->student_id, fn ($q) => $q->where('student_id', $participant->student_id))
            ->when(! $participant->student_id, fn ($q) => $q->where('teacher_id', $participant->teacher_id))
            ->with('registration.item')
            ->get()
            ->unique(fn (FestParticipant $p) => $p->registration_id);

        $marksByParticipant = FestMark::whereIn('participant_id', $entries->pluck('id'))
            ->get()
            ->keyBy('participant_id');

        // Same "has any marks recorded" gate item-finder.blade.php's item grid uses on
        // its own Results link (see FestPortalController::itemFinder()'s
        // $resultedItemIds) -- an item can be flagged results_published_at with zero
        // marks ever entered (published too early, or a no-show item), so $itemVisible
        // alone below isn't enough to avoid linking into a page that just says "No
        // published results for this item."
        $resultedItemIds = FestMark::whereIn('item_id', $entries->pluck('registration.item_id')->filter()->unique())
            ->distinct()
            ->pluck('item_id');

        $classGroupLabels = \App\Support\FestClassGroupScheme::labels(null, $event->rootEvent());

        return $entries
            ->map(function (FestParticipant $p) use ($eventsById, $event, $marksByParticipant, $resultedItemIds, $isAdminPreview, $classGroupLabels) {
                $item = $p->registration?->item;
                if (! $item) {
                    return null;
                }

                // This entry's OWN leaf event, not necessarily $event — a sibling phase
                // can be published/admin-previewable independently of the one the
                // participant page itself was opened from. Resolved through the
                // registration (authoritative), not $p->event_id (sometimes null).
                $itemEvent = $eventsById->get($p->registration?->event_id ?? $p->event_id) ?? $event;

                // Computed per-item, not once for the whole list — this participant can be
                // registered for several items with different individual publish states,
                // and a global $showMarks would have shown/hidden every one of them
                // identically regardless of which items had actually been published.
                $showMarks = $this->showIndividualMarks($itemEvent, $isAdminPreview, $item);
                $itemVisible = $isAdminPreview || app(FestItemResultsService::class)->isItemVisible($item, $itemEvent);
                $mark = $marksByParticipant->get($p->id);
                // pointsForMark() recalculates and OVERWRITES $mark->grade in place as a
                // side effect — capture the judge-entered grade before calling it, or the
                // 'grade' field below would show the recalculated value instead.
                $originalGrade = $mark?->grade;
                $points = $showMarks && $mark ? $this->gradePoints->pointsForMark($itemEvent, $mark) : null;

                return [
                    'item_id'          => $item->id,
                    'item_title'       => $item->title,
                    'event_title'      => $itemEvent->id !== $event->id ? $itemEvent->title : null,
                    'category_label'   => FestItemCategoryLabel::resolve($item, $classGroupLabels, config('fest_item_taxonomy.arts_category', [])),
                    'gender_label'     => \App\Support\FestSportsAgeGroup::genderLabel($item->gender),
                    'participant_type' => $item->participant_type ?: 'individual',
                    'is_team_item'     => $item->isTeamItem(),
                    'position'         => $showMarks ? $mark?->position : null,
                    'grade'            => $showMarks ? $originalGrade : null,
                    'points'           => $points,
                    'result'           => $showMarks ? trim(($mark?->measurement_value ?? '').' '.($mark?->measurement_unit ?? '')) : null,
                    'disqualified'     => (bool) $p->disqualified_at,
                    'results_url'      => ($itemVisible && $resultedItemIds->contains($item->id)) ? route('tenant.fest.item-results', [$itemEvent->id, $item->id]) : null,
                ];
            })
            ->filter()
            ->sortBy('item_title')
            ->values()
            ->all();
    }

    public function isPublicAudience(string $audience): bool
    {
        return $audience === 'public';
    }

    public function showSchedulePublicly(FestEvent $event): bool
    {
        return (bool) $event->schedule_published;
    }

    /** @return array{reference: string, name: ?string, school: ?string, order: ?int, team_name: ?string, group_id: ?int} */
    public function formatReportRow(FestEvent $event, FestParticipant $participant, string $audience = 'staff', ?FestSchedule $schedule = null): array
    {
        $public = $this->isPublicAudience($audience);
        $showName = ! $public || $this->showParticipantName($event, $participant, $participant->registration?->item);
        $showSchool = ! $public || $this->showSchoolName($event);

        $teamName = $participant->group?->team_name ?? $participant->registration?->team_name;
        $schoolName = $participant->registration?->school?->name;
        if (! $schoolName && $participant->registration?->school_id) {
            $schoolName = Tenant::find($participant->registration->school_id)?->name;
        }

        $item = $participant->registration?->item;
        $classGroupLabels = \App\Support\FestClassGroupScheme::labels(null, $event->rootEvent());

        $category = FestItemCategoryLabel::resolve($item, $classGroupLabels) ?? 'General Category';

        $itemType = match (strtolower((string) $item?->participant_type)) {
            'group' => 'Group Item',
            'team'  => 'Team Item',
            default => 'Individual Item',
        };

        $gender = match (strtolower((string) $item?->gender)) {
            'boys', 'male'    => 'Boys',
            'girls', 'female' => 'Girls',
            'mixed'           => 'Mixed',
            default           => 'General (Boys & Girls)',
        };

        $chestNo = $participant->group?->chest_no
            ?? $participant->chest_no
            ?? app(FestNumberingService::class)->effectiveChestNumber($participant)
            ?? $participant->level_registration_number;

        $reference = $public
            ? $this->publicReference($event, $participant)
            : ($chestNo ? (string) $chestNo : '—');

        return [
            'reference'     => $reference,
            'name'          => $showName ? ($participant->student?->name ?? $participant->teacher?->name) : null,
            'school'        => $showSchool ? ($schoolName ?? '') : null,
            'order'         => $schedule?->sort_order,
            // Mark Entry's unique-per-item Order No (distinct from `order` above, which is
            // the stage schedule's own sort_order) — null when not yet assigned, which the
            // attendance sheet prints as a blank cell rather than a placeholder dash.
            'order_no'      => $participant->group?->order_no ?? $participant->order_no,
            'item'          => $participant->registration?->item?->title,
            'item_category' => $category,
            'item_type'     => $itemType,
            'item_gender'   => $gender,
            'team_name'     => $showName ? $teamName : null,
            'group_id'      => $participant->group_id,
        ];
    }

    public function findParticipantByRef(FestEvent $event, string $ref): ?FestParticipant
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }

        $candidates = FestParticipant::whereHas('registration', fn ($r) => $r
            ->where('event_id', $event->id)
            ->where('status', 'approved'))
            ->with(['student', 'teacher', 'registration.item', 'registration.event', 'group']);

        // All newly generated links use this explicit participant-row reference. Keep
        // the older chest/level-registration formats below so bookmarks and printed QR
        // codes created before this change continue to resolve.
        if (preg_match('/^p-(\d+)$/i', $ref, $match)) {
            return (clone $candidates)->whereKey((int) $match[1])->first();
        }

        if (! ctype_digit($ref)) {
            return $candidates->where('level_registration_number', $ref)->first();
        }

        // Fast path: chest number has been officially revealed and persisted to the column.
        $found = (clone $candidates)->where('chest_no', (int) $ref)->first();
        if ($found) {
            return $found;
        }

        // Before reveal, chest_no's accessor (and every public-facing chest label built from
        // it — participantLinkRef(), publicReference()) falls back to a *computed* preview
        // number via FestNumberingService::effectiveChestNumber(), which isn't a persisted
        // column and so can't be matched in SQL. Resolve it the same way here so links built
        // from that preview number actually resolve instead of 404ing until reveal.
        $numbering = app(FestNumberingService::class);
        $found = $candidates->get()
            ->first(fn (FestParticipant $p) => $numbering->effectiveChestNumber($p) === (int) $ref);
        if ($found) {
            return $found;
        }

        // participantLinkRef() falls back to level_registration_number whenever chest_no
        // isn't set, with no assumption about whether that number happens to be all-digits
        // — so a digit-only $ref isn't necessarily a chest number. Try it as a level
        // registration number too before giving up, or a link this service itself built
        // (from a participant with no chest_no and an all-digit level_registration_number)
        // 404s.
        return (clone $candidates)->where('level_registration_number', $ref)->first();
    }
}
