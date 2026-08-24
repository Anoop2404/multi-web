<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Services\Events\FestPhaseLifecycleService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EventLifecycleGate
{
    public static function allowRegistration(FestEvent $event): void
    {
        if ($event->registration_locked) {
            throw new HttpException(422, 'Registration is locked for this event.');
        }

        if (! $event->isRegistrationOpen()) {
            throw new HttpException(422, 'Registration is not open for this event.');
        }
    }

    /**
     * Phase 6 (plan §6.3 item 1) — item-aware variant. Deliberately NOT wired into any
     * existing call site: allowRegistration($event) above is used by
     * FestRegistrationCreateService, the school registration API, and
     * FestRegistrationController today, all live paths real schools depend on, and this
     * change has not been run against the test suite (no PHP runtime available in the
     * environment this was written in — see the implementation's final status report).
     * Swapping those call sites to this method is a follow-up once that's been verified,
     * not something to do blind.
     *
     * When $event->phase_mode_enabled is true and $item is supplied, this additionally
     * enforces the item's own named-phase registration window via
     * FestPhaseLifecycleService instead of (not in addition to) the event-level check —
     * an item's phase is authoritative once phase mode is on (plan §6.3: "If phase mode
     * is on, use the item's phase lifecycle"). Falls back to allowRegistration($event)
     * when phase mode is off or no item is given, so behavior is unchanged for every
     * event that hasn't opted into phase mode (which, per the audit at the time this was
     * written, is every existing event — phase_mode_enabled defaults false).
     */
    public static function allowRegistrationForItem(FestEvent $event, ?FestEventItem $item = null): void
    {
        if ($item && $item->event_id !== $event->id) {
            throw new HttpException(403, 'The item does not belong to this event.');
        }

        if (! $event->phase_mode_enabled || ! $item) {
            self::allowRegistration($event);

            return;
        }

        $lifecycle = app(FestPhaseLifecycleService::class)->effectiveLifecycleForItem($item);

        if (! ($lifecycle->registration_batch_open ?? true)) {
            throw new HttpException(422, 'Registration is not open for this item\'s payment level.');
        }

        if ($lifecycle->registration_locked) {
            throw new HttpException(422, 'Registration is locked for this item\'s competition phase.');
        }

        if (! in_array($lifecycle->status, ['registration_open', 'published'], true)) {
            throw new HttpException(422, 'Registration is not open for this item\'s competition phase.');
        }

        $now = now();
        if ($lifecycle->registration_open && $now->lt($lifecycle->registration_open)) {
            throw new HttpException(422, 'Registration has not opened yet for this item\'s competition phase.');
        }
        if ($lifecycle->registration_close && $now->gt($lifecycle->registration_close)) {
            throw new HttpException(422, 'Registration has closed for this item\'s competition phase.');
        }
    }

    /** Staff review of submitted registrations (approve/reject). Pass override=true to bypass closed registration. */
    public static function allowRegistrationReview(FestEvent $event, bool $override = false): void
    {
        if ($override) {
            return;
        }

        if ($event->registration_locked) {
            throw new HttpException(422, 'Registration is locked. Use override to approve late entries.');
        }

        if ($event->results_published || $event->status === 'completed') {
            throw new HttpException(422, 'Registration review is closed after results are published.');
        }
    }

    public static function currentReportPhase(FestEvent $event): string
    {
        if ($event->results_published || $event->status === 'completed') {
            return 'after';
        }

        if ($event->schedule_published || $event->status === 'ongoing') {
            return 'during';
        }

        return 'before';
    }

    /**
     * Staff/admin report access is no longer gated by event lifecycle phase — organizers
     * need to be able to cross-verify data at any point (before, during, or after an
     * event), not just once it reaches a certain stage. currentReportPhase() above is
     * unaffected and still drives the informational "current phase" badge shown on
     * report pages. See Documents/Fest_Improvements_Proposal.md §3.8.
     *
     * @return list<string>
     */
    public static function allowedReportPhases(FestEvent $event): array
    {
        return ['before', 'during', 'after'];
    }

    public static function allowReportLifecyclePhase(FestEvent $event, string $phase, bool $override = false): void
    {
        if ($override || in_array($phase, self::allowedReportPhases($event), true)) {
            return;
        }

        throw new HttpException(403, 'This report is not available yet for the current event lifecycle.');
    }

    public static function allowMarkEntry(FestEvent $event): void
    {
        if ($event->scoring_locked) {
            throw new HttpException(422, 'Scoring is locked for this event.');
        }

        // Explicit check, not just a side effect of publish() also flipping status to
        // 'completed' (which the status check below would otherwise catch indirectly) —
        // a future publish path that sets results_published without transitioning status
        // must not leave mark entry silently open.
        if ($event->results_published) {
            throw new HttpException(422, 'Results have already been published for this event.');
        }

        if (! in_array($event->status, ['ongoing', 'registration_open', 'published'], true)) {
            throw new HttpException(422, 'Mark entry is not allowed in the current event phase.');
        }
    }

    /**
     * Phase 6 (plan §6.3 item 4) — item-aware variant, same not-yet-wired-in status as
     * allowRegistrationForItem() above: allowMarkEntry($event) has six live call sites
     * today (judge portal, mark coordinator, marks import, sahodaya mark entry) and this
     * hasn't been run against the test suite. Available for those call sites to adopt
     * once verified.
     */
    public static function allowMarkEntryForItem(FestEvent $event, ?FestEventItem $item = null): void
    {
        if ($item && $item->event_id !== $event->id) {
            throw new HttpException(403, 'The item does not belong to this event.');
        }

        // Once an item's results are published, its marks are frozen — the item was
        // previously just a visibility flag with no effect on Mark Entry, so an edit
        // after publish would go straight to the public scoreboard with no gate, no
        // warning, and no audit distinction from a pre-publish entry. Unpublish the
        // item first (FestItemResultsService::unpublishItem()) to make corrections.
        if ($item && $item->results_published_at) {
            throw new HttpException(422, 'This item\'s results are already published. Unpublish it first to edit marks.');
        }

        if (! $event->phase_mode_enabled || ! $item) {
            self::allowMarkEntry($event);

            return;
        }

        $lifecycle = app(FestPhaseLifecycleService::class)->effectiveLifecycleForItem($item);

        if ($lifecycle->scoring_locked) {
            throw new HttpException(422, 'Scoring is locked for this item\'s competition phase.');
        }

        if (! in_array($lifecycle->status, ['ongoing', 'registration_open', 'published'], true)) {
            throw new HttpException(422, 'Mark entry is not allowed in this item\'s current competition phase.');
        }
    }

    public static function allowAppealForParticipant(FestEvent $event, FestParticipant $participant): void
    {
        $participant->loadMissing('registration.item');
        $item = $participant->registration?->item;
        if (! $item || $participant->registration?->event_id !== $event->id) {
            throw new HttpException(403, 'Participant does not belong to this operational event.');
        }

        if (! $event->phase_mode_enabled) {
            if (! $event->appeals_open) {
                throw new HttpException(422, 'Appeals are not open for this event.');
            }

            return;
        }

        $lifecycle = app(FestPhaseLifecycleService::class)->effectiveLifecycleForItem($item);
        if (! $lifecycle->results_published || ! $lifecycle->appeals_open) {
            throw new HttpException(422, 'Appeals are not open for this competition phase.');
        }
        if ($lifecycle->appeal_deadline_at && now()->gt($lifecycle->appeal_deadline_at)) {
            throw new HttpException(422, 'The appeal deadline for this competition phase has passed.');
        }
    }

    public static function allowSchedulePublish(FestEvent $event): void
    {
        if ($event->schedule_published) {
            throw new HttpException(422, 'Schedule is already published.');
        }
    }

    public static function allowPublicSchedule(FestEvent $event): void
    {
        if (! $event->schedule_published) {
            throw new HttpException(404, 'Schedule is not published yet.');
        }
    }

    public static function allowReportExport(FestEvent $event, string $exportType, string $audience = 'staff'): void
    {
        if ($audience === 'staff') {
            return;
        }

        $staffOnly = [
            'registration-list', 'registrations', 'admit-cards', 'clashes', 'clashes-school',
            'fees', 'students', 'student-participation', 'promotions', 'certificate-counts', 'catering',
            'catering-by-school', 'volunteer-roster', 'id-cards-by-head', 'audit-log-extract',
        ];

        if (in_array($exportType, $staffOnly, true)) {
            throw new HttpException(422, 'This report is staff-only and cannot be exported for public distribution.');
        }
    }

    /**
     * Previously blocked staff/school-admin result-report downloads until
     * event->results_published — this only ever gated the authenticated report-catalog
     * exports (FestReportService, SahodayaAdmin/SchoolAdmin report controllers), a
     * separate surface from the public portal (Public\FestPortalController), which
     * enforces its own results_published checks independently and is unaffected by this.
     * Organizers need to be able to cross-verify result data before it's published, not
     * just after. See Documents/Fest_Improvements_Proposal.md §3.8.
     */
    public static function allowResultReport(FestEvent $event, string $exportType): void
    {
        //
    }

    public static function allowPublishResults(FestEvent $event): void
    {
        if ($event->results_published) {
            throw new HttpException(422, 'Results are already published.');
        }

        app(FestJudgeGateService::class)->assertCanPublish($event);

        if ($event->require_all_marks_before_publish) {
            self::assertAllParticipantsMarked($event);
        }
    }

    /** Block publishing an event that schools would see as empty. */
    public static function assertCanPublishEvent(FestEvent $event, ?string $venue = null, $eventStart = null): void
    {
        if (! $event->items()->exists()) {
            throw new HttpException(422, 'Add at least one competition item before publishing this event.');
        }

        $venueValue = $venue ?? $event->venue;
        $startValue = $eventStart ?? $event->event_start;

        if (! filled($venueValue) && ! filled($startValue)) {
            throw new HttpException(422, 'Set a venue or event start date before publishing.');
        }
    }

    private static function assertAllParticipantsMarked(FestEvent $event): void
    {
        // A partitioned hub's registrations/marks live on its region/finale children
        // (see FestRegistrationCreateService/FestRegistrationRouterService), never the
        // hub's own event_id — filtering by the hub id alone found zero participants and
        // silently passed this gate (Phase 3 audit item 1: "prevent hub result
        // publication until every applicable child is fully marked"). reportableEventIds()
        // is a no-op ([$event->id]) for anything that isn't a partitioned hub.
        $eventIds = $event->reportableEventIds();

        $participantCount = FestParticipant::whereHas('registration', fn ($q) => $q
            ->whereIn('event_id', $eventIds)
            ->where('status', 'approved'))
            ->where(function ($q) {
                $q->where('participant_role', 'performer')->orWhereNull('participant_role');
            })
            ->count();

        $markedCount = FestMark::whereIn('event_id', $eventIds)
            ->where(function ($q) {
                $q->whereNotNull('grade')->orWhereNotNull('score')->orWhereNotNull('position');
            })
            ->count();

        if ($participantCount > 0 && $markedCount < $participantCount) {
            throw new HttpException(422, "Mark entry incomplete ({$markedCount}/{$participantCount}). Complete all marks before publishing.");
        }
    }
}
