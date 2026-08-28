<?php

namespace App\Services\Events;

use App\Models\FeeReceipt;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestItemHead;
use App\Models\FestLevelRegistration;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\FestSchoolEventFeeLine;
use App\Models\FestStateProgram;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Fees\FeeReceiptAttachmentService;
use App\Support\FestClassGroupScheme;
use App\Support\FestSportsAgeGroup;
use App\Support\SchoolClassCategoryResolver;
use App\Support\TenantStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FestSchoolEventFeeService
{
    public function __construct(
        private FestEventFeeResolver $feeResolver,
        private FestItemFeeResolver $itemFeeResolver,
        private FestSportsCompositeFeeService $sportsCompositeFeeService,
    ) {}

    public function feeRequired(FestEvent $event): bool
    {
        if ($event->usesPhasedRegionalBilling()) {
            return $event->rootEvent()->registrationBatches()->exists();
        }

        $schedule = $this->resolveSchedule($event);

        return ($schedule['fee_model'] ?? 'none') !== 'none';
    }

    /**
     * The event whose FestSchoolEventFee rows actually hold a school's fee/payment state
     * for $event: the partitioned hub, if $event is one of its region/finale children —
     * otherwise $event itself, unchanged. recalculate() (below) always persists a
     * partitioned child's fee record under the HUB's event_id, mirroring resolveSchedule()'s
     * own redirect for fee configuration. Every read that queries FestSchoolEventFee
     * directly by event_id (isPaid(), hasApprovedPaymentForRegistration(),
     * currentFeeRecordFor(), itemPaymentAllocation()) MUST go through this first, or it
     * silently queries a row that only ever exists under the hub's id, and gets nothing
     * back — even for a school that has fully paid. This was one of the concrete Phase 2
     * gaps: several of these reads had no such redirect while resolveSchedule()/
     * recalculate() already did, so passing a child event (which is what schools and some
     * portal/admin surfaces actually operate against day to day) silently reported "unpaid"
     * or "no fee record" regardless of the real, hub-owned state.
     */
    public function feeOwnerEvent(FestEvent $event): FestEvent
    {
        if ($event->parent_event_id) {
            $hub = FestEvent::find($event->parent_event_id);
            if ($hub && ($hub->conduct_mode ?? 'standard') === 'partitioned') {
                return $hub;
            }
        }

        return $event;
    }

    /** @return array<string, mixed> */
    public function resolveSchedule(FestEvent $event): array
    {
        // Regional registrations live on a partition child, while the fee schedule and the
        // school's single invoice live on the parent hub — recalculate() below has always
        // redirected fee CALCULATION to the hub for a partitioned child, but nothing here
        // did the same for fee CONFIGURATION lookup. A child event's own fee_settings
        // column is normally empty (fee settings are only ever configured on the hub via
        // Settings → Fee settings), so resolveSchedule($child) fell through to fee_model
        // 'none' and feeRequired($child) reported false — even though the hub had a fully
        // configured schedule. That silently hid the Billing & Payment tab (and the item
        // fee column) for any school looking at their region child event directly, which
        // is the normal/only way schools reach their event once redirected off the hub
        // (see FestRegistrationController::redirectHubToSchoolPartition()). Mirror the
        // exact same redirect recalculate() already does so schedule resolution and actual
        // billing always agree.
        if ($event->parent_event_id) {
            $hub = FestEvent::find($event->parent_event_id);
            if ($hub && ($hub->conduct_mode ?? 'standard') === 'partitioned') {
                return $this->resolveSchedule($hub);
            }
        }

        $schedule = null;

        if ($event->state_program_id) {
            $program = FestStateProgram::find($event->state_program_id);
            $level = $event->level_round ?? 'sahodaya';
            $levelFees = $program?->level_fees[$level] ?? null;
            if (is_array($levelFees) && filled($levelFees['fee_model'] ?? null)) {
                $schedule = array_merge(
                    config("fest_fees.level_defaults.{$level}", []),
                    $levelFees
                );
            }
        }

        if ($schedule === null) {
            $legacy = $this->feeResolver->resolveForEvent($event);
            if (($legacy['fee_type'] ?? 'none') !== 'none') {
                $schedule = [
                    'fee_model' => 'per_item',
                    'per_item_amount' => (float) ($legacy['fee_amount'] ?? 0),
                ];
            } else {
                // Legacy fee_type none with no fee_settings = no fee until configured in event settings.
                $schedule = ['fee_model' => 'none'];
            }
        }

        if (is_array($event->fee_settings) && filled($event->fee_settings)) {
            $schedule = array_merge($schedule, $event->fee_settings);
        }

        if ($event->event_type === 'sports') {
            $sportsDefaults = config('fest_fees.level_defaults.sports', []);
            $schedule = array_merge($sportsDefaults, $schedule);
            $schedule['fee_model'] = 'sports_composite';

            // Head = Event: once fees are configured on the sport event (Settings →
            // Fee settings), its unified columns are the single source of truth.
            // Blank columns then mean ₹0 — NOT "fall back to config defaults",
            // otherwise schools see phantom ₹300/₹150 charges the admin never set.
            if ($event->hasSportsFeesConfigured()) {
                $schedule['school_registration_flat'] = (float) ($event->school_registration_fee ?? 0);
                $schedule['per_student_amount'] = (float) ($event->student_registration_fee ?? 0);
                $schedule['team_registration_fee'] = (float) ($event->team_registration_fee ?? 0);
                $schedule['default_item_fee'] = (float) ($event->default_item_fee ?? 0);
                $schedule['extra_item_fee'] = $event->extra_item_fee !== null
                    ? (float) $event->extra_item_fee
                    : ($schedule['extra_item_fee'] ?? null);
                $schedule['included_items_per_student'] = (int) ($event->included_items_per_student ?? 0);
                $schedule['included_teams'] = (int) ($event->included_teams ?? 0);
            }
        }

        if (($schedule['fee_model'] ?? '') === 'item_catalog') {
            $scheme = FestClassGroupScheme::resolveForEvent($event, $schedule);
            $schedule['class_group_scheme'] = $scheme;
            $schedule['class_group_fees'] = array_merge(
                FestClassGroupScheme::defaultFees($scheme, $event),
                $schedule['class_group_fees'] ?? []
            );
            if ($event->event_type === 'sports') {
                $schedule['age_group_fees'] = array_merge(
                    FestSportsAgeGroup::defaultFees($event->tenant_id),
                    $schedule['age_group_fees'] ?? []
                );
            }
            $schedule['participant_type_fees'] = array_merge(
                config('fest_fees.default_participant_type_fees', []),
                $schedule['participant_type_fees'] ?? []
            );
        }

        if (($schedule['fee_model'] ?? 'none') === 'none' && $this->eventHasConfiguredItemFees($event)) {
            $schedule['fee_model'] = 'item_catalog';
            $schedule = $this->applyItemCatalogDefaults($event, $schedule);
        }

        return $schedule;
    }

    private function eventHasConfiguredItemFees(FestEvent $event): bool
    {
        if ($event->event_type === 'sports' && $event->hasSportsFeesConfigured()) {
            return true;
        }

        if (FestEventItem::query()
            ->where('event_id', $event->id)
            ->where(function ($q) {
                $q->where('is_enabled', true)->orWhereNull('is_enabled');
            })
            ->whereNotNull('fee_amount')
            ->where('fee_amount', '>', 0)
            ->exists()) {
            return true;
        }

        return FestItemHead::query()
            ->where('event_id', $event->id)
            ->where(function ($q) {
                $q->where(fn ($inner) => $inner->whereNotNull('default_item_fee')->where('default_item_fee', '>', 0))
                    ->orWhere(fn ($inner) => $inner->whereNotNull('extra_item_fee')->where('extra_item_fee', '>', 0));
            })
            ->exists();
    }

    /** @param  array<string, mixed>  $schedule */
    private function applyItemCatalogDefaults(FestEvent $event, array $schedule): array
    {
        $scheme = FestClassGroupScheme::resolveForEvent($event, $schedule);
        $schedule['class_group_scheme'] = $scheme;
        $schedule['class_group_fees'] = array_merge(
            FestClassGroupScheme::defaultFees($scheme, $event),
            $schedule['class_group_fees'] ?? []
        );
        if ($event->event_type === 'sports') {
            $schedule['age_group_fees'] = array_merge(
                FestSportsAgeGroup::defaultFees($event->tenant_id),
                $schedule['age_group_fees'] ?? []
            );
        }
        $schedule['participant_type_fees'] = array_merge(
            config('fest_fees.default_participant_type_fees', []),
            $schedule['participant_type_fees'] ?? []
        );

        return $schedule;
    }

    /**
     * Push a partitioned hub's fee configuration down onto every one of its partition
     * children (region/cluster/finale/sports_discipline).
     *
     * resolveSchedule() above already redirects a child's schedule LOOKUP up to the hub, so
     * class-group/age-group/participant-type rates and the other schedule-level settings in
     * fee_settings were already applying correctly. But that redirect never touches three
     * other things schools' registrations actually reference on the CHILD's own rows: the
     * child's own fee_settings column (left stale/empty), each region's own copy of
     * FestEventItem (item-level fee_amount overrides — see FestItemSyncService::
     * copyItemsToPartition(), every partition gets its own item rows), and each region's own
     * FestItemHead rows. An admin setting a per-item or per-head fee override — or saving fee
     * settings at all — on the hub therefore had no effect on any region a school actually
     * registers and pays under. This makes a hub-level save apply everywhere, matching what
     * admins expect from "Sahodaya applies settings across the parent event."
     *
     * Also recalculates (and persists) every already-registered school's FestSchoolEventFee
     * on each child immediately — see the loop at the bottom — so this applies retroactively
     * to schools that registered before the fee change, not just new registrations going
     * forward. If a school was already fully paid and the new fee is higher, recalculate()'s
     * existing demoteSiblingApprovals() safety net demotes their approved registrations back
     * to 'submitted' until the difference is settled, exactly as it would for any other
     * post-approval fee increase.
     *
     * No-op for anything that isn't a partitioned hub, or has no children yet. Deliberately
     * one-directional (hub → children): editing fee settings on a CHILD event already takes
     * effect for that child directly (it owns its own FestEventItem/FestItemHead rows), so
     * there is nothing to redirect the other way.
     *
     * Skips any child with hasCustomizedFees() set — once a Sahodaya admin has edited that
     * child's own fee_settings, an item fee, or a head's fee columns directly (stamped by
     * FestEventSettingsController::updateFeeSettings()/updateItemFee() and
     * FestItemHeadController::updateWindows()), this cascade used to silently revert that
     * edit back to the hub's values the next time hub fees were saved — including on every
     * ordinary school registration routed through FestRegistrationCreateService, which
     * refreshes a child's items via FestItemSyncService::copyItemToPartition(). A child that
     * hasn't customized anything keeps inheriting hub changes exactly as before.
     */
    public function propagateFeeSettingsToChildren(FestEvent $hub): void
    {
        if (($hub->conduct_mode ?? 'standard') !== 'partitioned') {
            return;
        }

        $children = FestEvent::where('parent_event_id', $hub->id)->get()
            ->reject(fn (FestEvent $child) => $child->hasCustomizedFees());
        if ($children->isEmpty()) {
            return;
        }

        $hubItems = FestEventItem::where('event_id', $hub->id)->get(['id', 'fee_amount']);
        $hubHeads = FestItemHead::where('event_id', $hub->id)->get();

        $sportsColumns = [
            'school_registration_fee', 'student_registration_fee', 'team_registration_fee',
            'default_item_fee', 'extra_item_fee', 'included_items_per_student', 'included_teams',
            'verification_policy', 'approval_policy', 'max_participants', 'max_teams',
        ];

        foreach ($children as $child) {
            $child->update(['fee_settings' => $hub->fee_settings]);

            if ($hub->event_type === 'sports') {
                $child->update(array_intersect_key($hub->getAttributes(), array_flip($sportsColumns)));
            }

            foreach ($hubItems as $hubItem) {
                FestEventItem::where('event_id', $child->id)
                    ->where('inherited_from_item_id', $hubItem->id)
                    ->update(['fee_amount' => $hubItem->fee_amount]);
            }

            foreach ($hubHeads as $hubHead) {
                // Partition children don't link heads back to the hub's head row (no
                // inherited_from_head_id column), so match by name — the same way titles
                // are matched for region partitions elsewhere in this topology.
                FestItemHead::where('event_id', $child->id)
                    ->where('name', $hubHead->name)
                    ->update([
                        'default_item_fee' => $hubHead->default_item_fee,
                        'extra_item_fee' => $hubHead->extra_item_fee,
                        'school_registration_fee' => $hubHead->school_registration_fee,
                        'student_registration_fee' => $hubHead->student_registration_fee,
                        'team_registration_fee' => $hubHead->team_registration_fee,
                        'included_items_per_student' => $hubHead->included_items_per_student,
                        'included_teams' => $hubHead->included_teams,
                        'verification_policy' => $hubHead->verification_policy,
                        'approval_policy' => $hubHead->approval_policy,
                        'max_participants' => $hubHead->max_participants,
                        'max_teams' => $hubHead->max_teams,
                    ]);
            }

            // Schools that already registered under this region shouldn't have to wait for
            // their next payment-page visit to see the corrected amount — recalculate() is
            // cheap, idempotent, and already has the "demote approved-but-now-underpaid
            // registrations back to submitted" safety net built in (see
            // demoteSiblingApprovals()), so it's safe to run eagerly here rather than leaving
            // stale total_due sitting on every already-registered school's fee record.
            FestRegistration::whereIn('event_id', $child->reportableEventIds())
                ->whereIn('status', ['submitted', 'approved', 'pending_approval'])
                ->distinct()
                ->pluck('school_id')
                ->each(fn (string $schoolId) => $this->recalculate($child, $schoolId));
        }
    }

    /** Which fee configuration source is active for this event. */
    public function feeConfigSource(FestEvent $event): string
    {
        if ($event->state_program_id) {
            $program = FestStateProgram::find($event->state_program_id);
            $level = $event->level_round ?? 'sahodaya';
            $levelFees = $program?->level_fees[$level] ?? null;
            if (is_array($levelFees) && filled($levelFees['fee_model'] ?? null)) {
                return 'state_program';
            }
        }

        $legacy = $this->feeResolver->resolveForEvent($event);
        if (($legacy['fee_type'] ?? 'none') !== 'none') {
            return 'legacy';
        }

        if (is_array($event->fee_settings) && filled($event->fee_settings)) {
            return 'event_settings';
        }

        return 'none';
    }

    public function schoolRegistrationAmount(Tenant $school, array $schedule): float
    {
        if (! ($schedule['include_school_registration'] ?? false)) {
            return 0;
        }

        // Tier is derived from the school's own active classes (SchoolClass ->
        // ClassCategory), not a manually-set institution_level tag — see
        // SchoolClassCategoryResolver and docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md §7.4.
        // school_registration is an arbitrary N-tier map keyed by whatever tier labels
        // the resolver produces; an event that only ever configured the original
        // 'secondary'/'senior_secondary' pair keeps behaving exactly as before, since
        // any tier the map doesn't have an entry for (e.g. 'other') falls back to
        // 'secondary' here, same as the old institution_level lookup already did.
        $tier = SchoolClassCategoryResolver::feeTierFor($school);

        $amounts = $schedule['school_registration'] ?? [];

        if (isset($schedule['override_amount'])) {
            return (float) $schedule['override_amount'];
        }

        return (float) ($amounts[$tier] ?? $amounts['secondary'] ?? 0);
    }

    public function participationFee(int $itemCount, array $schedule): float
    {
        if ($itemCount <= 0) {
            return 0;
        }

        $first = (float) ($schedule['first_item'] ?? 350);
        $additional = (float) ($schedule['additional_item'] ?? 100);

        return $first + max(0, $itemCount - 1) * $additional;
    }

    /**
     * 'student_count_slab' fee model — bills a school a single stepped amount based on a
     * student count, per a slab table configured in fee_settings.student_count_slabs (list
     * of {min_count, max_count, amount}; a null max_count means "and above"). Per-Sahodaya,
     * per-event-type by construction, since it's just more of that event's own fee_settings
     * JSON — see docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md §7.4.
     *
     * $studentCount's source depends on fee_settings.student_count_slab_basis — either the
     * school's students registered for this event (default) or its whole active-student
     * enrollment — resolved by callers via studentCountSlabBasisCount() before this is called.
     *
     * A count landing outside every configured range (gaps, or slabs that don't cover
     * 0..∞) falls back to the highest configured slab rather than silently billing ₹0 —
     * the same "always resolve to something rather than a silent zero" fallback
     * schoolRegistrationAmount() already uses for an unconfigured tier.
     */
    public function studentCountSlabFee(int $studentCount, array $schedule): float
    {
        $slabs = collect($schedule['student_count_slabs'] ?? [])
            ->filter(fn ($slab) => is_array($slab) && isset($slab['amount']) && $slab['amount'] !== '')
            ->map(fn ($slab) => [
                'min_count' => (int) ($slab['min_count'] ?? 0),
                'max_count' => isset($slab['max_count']) && $slab['max_count'] !== '' && $slab['max_count'] !== null
                    ? (int) $slab['max_count']
                    : null,
                'amount' => (float) $slab['amount'],
            ])
            ->sortBy('min_count')
            ->values();

        if ($slabs->isEmpty()) {
            return 0.0;
        }

        $match = $slabs->first(fn ($slab) => $studentCount >= $slab['min_count']
            && ($slab['max_count'] === null || $studentCount <= $slab['max_count']));

        return (float) ($match['amount'] ?? $slabs->last()['amount']);
    }

    /**
     * Which count to look up a 'student_count_slab' bracket by, per
     * fee_settings.student_count_slab_basis: the school's students registered for this event
     * (default — same $eventRegisteredCount already computed by the caller), or the school's
     * whole active-student enrollment regardless of event registration. Only the slab bracket
     * changes basis this way — the optional per-student surcharge always bills against actual
     * registered students, so callers keep using $eventRegisteredCount for that term.
     */
    private function studentCountSlabBasisCount(string $schoolId, array $schedule, int $eventRegisteredCount): int
    {
        if (($schedule['student_count_slab_basis'] ?? 'event_registrations') !== 'school_total_enrollment') {
            return $eventRegisteredCount;
        }

        return Student::where('tenant_id', $schoolId)->where('status', 'active')->count();
    }

    /** @param  ?int  $phaseId  When given, count only items assigned to this FestEventPhase (see recalculateForPhase()). */
    public function billableItemCount(FestEvent $event, string $schoolId, array $schedule = [], ?int $phaseId = null): int
    {
        $count = FestRegistration::whereIn('event_id', $event->reportableEventIds())
            ->where('school_id', $schoolId)
            ->whereIn('status', ['submitted', 'approved', 'pending_approval'])
            ->whereHas('item', fn ($q) => $q->where('is_enabled', true)
                ->when($phaseId !== null, fn ($iq) => $iq->where('phase_id', $phaseId)))
            ->count();

        if (! ($schedule['charge_standbys'] ?? false)) {
            return $count;
        }

        $standbys = FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->whereIn('event_id', $event->reportableEventIds())
                ->where('school_id', $schoolId)
                ->whereIn('status', ['submitted', 'approved', 'pending_approval'])
                ->when($phaseId !== null, fn ($q2) => $q2->whereHas('item', fn ($iq) => $iq->where('phase_id', $phaseId))))
            ->where('participant_role', 'standby')
            ->count();

        return $count + $standbys;
    }

    public function standbyParticipantCount(FestEvent $event, string $schoolId): int
    {
        return FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->whereIn('event_id', $event->reportableEventIds())
                ->where('school_id', $schoolId)
                ->whereIn('status', ['submitted', 'approved', 'pending_approval']))
            ->where('participant_role', 'standby')
            ->count();
    }

    /** @param  ?int  $phaseId  When given, count only participants on items assigned to this FestEventPhase (see recalculateForPhase()). */
    public function billableStudentCount(FestEvent $event, string $schoolId, ?int $phaseId = null): int
    {
        return FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->whereIn('event_id', $event->reportableEventIds())
                ->where('school_id', $schoolId)
                ->whereIn('status', ['submitted', 'approved', 'pending_approval'])
                ->when($phaseId !== null, fn ($q2) => $q2->whereHas('item', fn ($iq) => $iq->where('phase_id', $phaseId))))
            ->where('participant_role', '!=', 'standby')
            ->where(fn ($q) => $q->whereNotNull('student_id')->orWhereNotNull('teacher_id'))
            ->get(['student_id', 'teacher_id'])
            ->map(fn (FestParticipant $p) => $p->student_id ?? $p->teacher_id)
            ->unique()
            ->filter()
            ->count();
    }

    /**
     * Whether this event bills sports_composite fees per Event Head.
     * After Head = Event unification, sports always bills at event level (returns false).
     * Legacy true only when heads exist, event fees are not yet on FestEvent, and head_id column exists.
     */
    public function usesPerHeadBilling(FestEvent $event): bool
    {
        if (($this->resolveSchedule($event)['fee_model'] ?? 'none') !== 'sports_composite') {
            return false;
        }

        // Unified sports: sport events always bill at event level (Head = Event).
        // Season hubs never use per-head once child sport events exist.
        if ($event->event_type === 'sports') {
            if ($event->isSportsDisciplineEvent() || $event->hasSportsFeesConfigured()) {
                return false;
            }
            // Transition: season hub only — per-head if heads still hang off the season and no discipline children.
            if ($event->isSportsSeasonEvent()) {
                $hasChildren = FestEvent::where('parent_event_id', $event->id)
                    ->where('partition_role', 'sports_discipline')
                    ->exists();
                if ($hasChildren) {
                    return false;
                }
            }
            if (! Schema::hasColumn('fest_school_event_fees', 'head_id')) {
                return false;
            }

            return FestItemHead::where('event_id', $event->id)->exists();
        }

        if (! Schema::hasColumn('fest_school_event_fees', 'head_id')) {
            return false;
        }

        return FestItemHead::where('event_id', $event->id)->exists();
    }

    /**
     * Recalculate sports_composite at event level (Head = Event).
     * Prefer this over recalculateForHead for sports sport events.
     */
    public function recalculateForSportsEvent(FestEvent $event, string $schoolId): FestSchoolEventFee
    {
        $composite = $this->sportsCompositeFeeService->calculateForEvent($event, $schoolId);
        $total = $composite['school_reg'] + $composite['student_reg'] + $composite['item_fee'] + $composite['team_fee'];

        $record = FestSchoolEventFee::firstOrNew([
            'event_id' => $event->id,
            'school_id' => $schoolId,
            'head_id' => null,
        ]);

        // Prefer null-head row; if only head-scoped rows remain, reuse the first.
        if (! $record->exists && Schema::hasColumn('fest_school_event_fees', 'head_id')) {
            $legacy = FestSchoolEventFee::where('event_id', $event->id)
                ->where('school_id', $schoolId)
                ->orderByRaw('head_id is null desc')
                ->first();
            if ($legacy) {
                $record = $legacy;
                $record->head_id = null;
            }
        }

        // Snapshot before overwriting total_due — see demoteSiblingApprovals() for why.
        $wasFullyPaidAndApproved = $record->exists && $record->status === 'approved' && $record->isFullyPaid();

        $record->fill([
            'head_id' => null,
            'school_registration_fee' => $composite['school_reg'],
            'student_registration_fee' => $composite['student_reg'],
            'participation_item_count' => $composite['student_count'],
            'participation_fee' => $composite['item_fee'] + $composite['team_fee'],
            'extra_item_fee' => $composite['team_fee'],
            'total_due' => round($total, 2),
        ]);
        $record->save();

        // Derive status from the actual receipt state (approved/uploaded/none) rather
        // than trusting whatever status happens to already be stored — previously a
        // status of 'approved' set while total_due was (incorrectly) 0 would stick
        // around forever afterward, even once the real amount was recalculated and
        // even if the school's uploaded proof was never actually approved by an admin.
        $record->refreshPaidState();
        $this->applyAvailableCredit($record, $event);

        if ($wasFullyPaidAndApproved && ! $record->isFullyPaid()) {
            $this->demoteSiblingApprovals($event, $schoolId, $record);
        }

        if ($this->supportsFeeLines()) {
            $this->syncFeeLines($record, $composite['lines']);
        }

        return $record;
    }

    /** Heads under this event that this school has (or previously had) billable activity for. */
    public function headsWithActivityForSchool(FestEvent $event, string $schoolId): Collection
    {
        return FestItemHead::where('event_id', $event->id)
            ->orderBy('sort_order')
            ->get()
            ->filter(function (FestItemHead $head) use ($event, $schoolId) {
                $hasRegistrations = FestRegistration::where('event_id', $event->id)
                    ->where('school_id', $schoolId)
                    ->whereIn('status', ['submitted', 'approved', 'pending_approval'])
                    ->whereHas('item', fn ($q) => $q->where('head_id', $head->id))
                    ->exists();

                if ($hasRegistrations) {
                    return true;
                }

                return FestSchoolEventFee::where('event_id', $event->id)
                    ->where('school_id', $schoolId)
                    ->where('head_id', $head->id)
                    ->exists();
            })
            ->values();
    }

    /** Recalculate (and persist) the fee record for one specific Event Head for one school. */
    public function recalculateForHead(FestEvent $event, string $schoolId, FestItemHead $head): FestSchoolEventFee
    {
        $composite = $this->sportsCompositeFeeService->calculateForHead($head, $schoolId);
        $total = $composite['school_reg'] + $composite['student_reg'] + $composite['item_fee'] + $composite['team_fee'];

        $record = FestSchoolEventFee::firstOrNew([
            'event_id' => $event->id,
            'school_id' => $schoolId,
            'head_id' => $head->id,
        ]);

        $record->fill([
            'school_registration_fee' => $composite['school_reg'],
            'student_registration_fee' => $composite['student_reg'],
            'participation_item_count' => $composite['student_count'],
            'participation_fee' => $composite['item_fee'] + $composite['team_fee'],
            'extra_item_fee' => $composite['team_fee'],
            'total_due' => round($total, 2),
        ]);
        $record->save();

        // See recalculateForSportsEvent() for why status is derived, not preserved.
        $record->refreshPaidState();

        if ($this->supportsFeeLines()) {
            $this->syncFeeLines($record, $composite['lines']);
        }

        return $record;
    }

    /**
     * Recalculate every head this school has activity under for this event.
     *
     * @return Collection<int, FestSchoolEventFee>
     */
    public function recalculateAllHeadsForSchool(FestEvent $event, string $schoolId): Collection
    {
        return $this->headsWithActivityForSchool($event, $schoolId)
            ->map(fn (FestItemHead $head) => $this->recalculateForHead($event, $schoolId, $head))
            ->values();
    }

    /** Is the fee for one specific Event Head fully paid (or not due)? */
    public function isHeadPaid(FestEvent $event, string $schoolId, int $headId): bool
    {
        $head = FestItemHead::find($headId);
        if (! $head || $head->event_id !== $event->id) {
            return true;
        }

        $fee = FestSchoolEventFee::where('event_id', $event->id)
            ->where('school_id', $schoolId)
            ->where('head_id', $headId)
            ->first();

        if (! $fee) {
            $fee = $this->recalculateForHead($event, $schoolId, $head);
        }

        return $fee->isFullyPaid();
    }

    /** Upload a payment proof against one specific Event Head's fee record. */
    public function attachPaymentForHead(
        FestEvent $event,
        string $schoolId,
        int $headId,
        UploadedFile $proof,
        int $userId,
        ?string $transactionRef = null,
        ?string $bankName = null,
        ?float $amount = null,
        array $extraProofs = [],
    ): FestSchoolEventFee {
        $head = FestItemHead::findOrFail($headId);
        abort_if($head->event_id !== $event->id, 403);

        $fee = $this->recalculateForHead($event, $schoolId, $head);
        abort_if($fee->total_due <= 0, 422, 'No fee due for this Event Head.');
        abort_if($fee->isFullyPaid(), 422, 'Fee already fully paid.');

        $outstanding = $fee->outstandingBalance();
        $payAmount = $amount !== null ? round($amount, 2) : $outstanding;
        abort_if($payAmount <= 0, 422, 'Payment amount must be greater than zero.');
        abort_if($payAmount > $outstanding, 422, 'Payment cannot exceed the outstanding balance of ₹'.number_format($outstanding, 2).'.');

        $path = TenantStorage::storeUploadedFile($proof, "fest-payments/{$schoolId}");

        FeeReceipt::supersedePriorForFeeable($fee);

        $receipt = FeeReceipt::create([
            'feeable_type' => FestSchoolEventFee::class,
            'feeable_id' => $fee->id,
            'file_path' => $path,
            'transaction_ref' => $transactionRef,
            'bank_name' => $bankName,
            'payment_date' => now()->toDateString(),
            'amount' => $payAmount,
            'status' => 'uploaded',
            'uploaded_by_user_id' => $userId,
        ]);

        // Extra evidence images for this same payment (e.g. a bank statement page alongside
        // a UTR screenshot) — see docs/FLOW_GAP_FIX_PLAN.md multi-image upload feature.
        // Never creates additional receipts; $proof above remains the one reviewed record.
        if (! empty($extraProofs)) {
            app(FeeReceiptAttachmentService::class)
                ->attachExtra($receipt, $extraProofs, "fest-payments/{$schoolId}");
        }

        $fee->update([
            'fee_receipt_id' => $receipt->id,
            'status' => 'proof_uploaded',
        ]);

        return $fee->fresh(['feeReceipt']);
    }

    /**
     * Whether this event bills Kalotsavam fees per named Phase instead of one event-wide
     * amount. Independent of usesPerHeadBilling() (that's the sports-only, fee_model =
     * sports_composite mechanism) — this is the general-purpose equivalent for any event
     * using phase_mode_enabled, keyed by phase_id instead of head_id. See
     * docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md §3.
     */
    public function usesPerPhaseBilling(FestEvent $event): bool
    {
        if ($event->usesPhasedRegionalBilling()) {
            return false;
        }

        if ($event->event_type === 'sports') {
            return false;
        }

        if (! ($event->phase_mode_enabled ?? false)) {
            return false;
        }

        if (! Schema::hasColumn('fest_school_event_fees', 'phase_id')) {
            return false;
        }

        return FestEventPhase::where('event_id', $event->id)->exists();
    }

    /** Phases under this event that this school has (or previously had) billable activity for. */
    public function phasesWithActivityForSchool(FestEvent $event, string $schoolId): Collection
    {
        return FestEventPhase::where('event_id', $event->id)
            ->orderBy('sort_order')
            ->get()
            ->filter(function (FestEventPhase $phase) use ($event, $schoolId) {
                $hasRegistrations = FestRegistration::where('event_id', $event->id)
                    ->where('school_id', $schoolId)
                    ->whereIn('status', ['submitted', 'approved', 'pending_approval'])
                    ->whereHas('item', fn ($q) => $q->where('phase_id', $phase->id))
                    ->exists();

                if ($hasRegistrations) {
                    return true;
                }

                return FestSchoolEventFee::where('event_id', $event->id)
                    ->where('school_id', $schoolId)
                    ->where('phase_id', $phase->id)
                    ->exists();
            })
            ->values();
    }

    /**
     * Pulls one phase's share out of a whole-event composite calculation already computed
     * once, unfiltered, by FestSportsCompositeFeeService::calculate() (see its
     * phase_attribution return key) — the per-student fee and item-quota mechanic are
     * computed ONCE across the whole event, never reset per phase, and only attributed to
     * individual phases here, for display/invoicing.
     *
     * @return array{student_reg_amount: float, student_reg_count: int, extra_item_amount: float, lines: list<array>}
     */
    private function compositeAttributionForPhase(array $composite, FestEventPhase $phase): array
    {
        $attribution = $composite['phase_attribution'] ?? [];
        $perStudentRate = (float) ($attribution['per_student_rate'] ?? 0.0);
        $studentBucket = $attribution['student_reg']['by_phase'][$phase->id] ?? ['amount' => 0.0, 'student_count' => 0];
        $studentRegAmount = round((float) ($studentBucket['amount'] ?? 0), 2);
        $studentRegCount = (int) ($studentBucket['student_count'] ?? 0);
        $extraItemAmount = round((float) ($attribution['extra_item']['by_phase'][$phase->id] ?? 0.0), 2);

        $lines = [];
        if ($studentRegAmount > 0) {
            $lines[] = [
                'line_type' => 'student_reg',
                'label' => "Student registration ({$phase->name}) — {$studentRegCount} × ₹".number_format($perStudentRate, 0),
                'quantity' => $studentRegCount,
                'unit_amount' => $perStudentRate,
                'amount' => $studentRegAmount,
                'meta' => ['event_id' => $phase->event_id, 'phase_id' => $phase->id],
            ];
        }

        foreach ($composite['lines'] ?? [] as $line) {
            if (in_array($line['line_type'] ?? null, ['item_fee', 'extra_item'], true)
                && ($line['meta']['phase_id'] ?? null) === $phase->id) {
                $lines[] = $line;
            }
        }

        return [
            'student_reg_amount' => $studentRegAmount,
            'student_reg_count' => $studentRegCount,
            'extra_item_amount' => $extraItemAmount,
            'lines' => $lines,
        ];
    }

    /**
     * Recalculate (and persist) the fee record for one specific named Phase for one school.
     * Independently payable via attachPaymentForPhase() — no gating between phases (confirmed
     * 2026-08-15, see plan doc §3 item 7): a school can register/pay a later phase without
     * having settled an earlier one first.
     */
    public function recalculateForPhase(FestEvent $event, string $schoolId, FestEventPhase $phase): FestSchoolEventFee
    {
        $schedule = $this->resolveSchedule($event);
        $feeModel = $schedule['fee_model'] ?? 'none';

        $hasActivity = FestRegistration::where('event_id', $event->id)
            ->where('school_id', $schoolId)
            ->whereIn('status', ['submitted', 'approved', 'pending_approval'])
            ->whereHas('item', fn ($q) => $q->where('phase_id', $phase->id))
            ->exists();

        $itemCount = $this->billableItemCount($event, $schoolId, $schedule, $phase->id);
        $studentCount = $this->billableStudentCount($event, $schoolId, $phase->id);

        // The school registration fee is owned by whichever phase(s) the Sahodaya configured
        // a share for (0 or null = this phase collects none of it) — see
        // FestEventPhase::school_registration_fee_share and plan doc §3 item 4. Supports both
        // a flat fee on one phase (e.g. MCS: full amount on "Level 1", 0 on "Level 2") and a
        // split across phases (e.g. half on each), purely by how shares are configured. Only
        // charged once this phase actually has registered activity, same guard the event-wide
        // recalculate() already applies for its own school registration fee. Unchanged for
        // kalolsavam_composite/sports_composite too — that model's own school-fee component is
        // deliberately not used here; the phase share is the single source of truth for the
        // school-level amount regardless of fee model.
        $schoolRegFee = $hasActivity ? (float) ($phase->school_registration_fee_share ?? 0) : 0.0;

        $useComposite = in_array($feeModel, ['sports_composite', 'kalolsavam_composite'], true)
            && $this->supportsSportsCompositeSchema();

        $studentRegFee = 0.0;
        $extraItemFee = 0.0;
        $compositeLines = [];
        $participationCount = $itemCount;

        if ($useComposite) {
            // Computed once, unfiltered, across the WHOLE event — never reset per phase — so
            // the included-item quota and per-student fee apply once per student for the
            // event, not once per phase. compositeAttributionForPhase() above pulls this
            // phase's share back out.
            $composite = $this->sportsCompositeFeeService->calculate($event, $schoolId, $schedule);
            $attribution = $this->compositeAttributionForPhase($composite, $phase);

            $studentRegFee = $attribution['student_reg_amount'];
            $extraItemFee = $attribution['extra_item_amount'];
            $participationFee = round($studentRegFee + $extraItemFee, 2);
            $participationCount = $attribution['student_reg_count'];
            $compositeLines = $attribution['lines'];
        } else {
            $participationFee = match ($feeModel) {
                'item_catalog' => $this->itemFeeResolver->participationTotal($event, $schoolId, $schedule, $phase->id),
                'cksc_tiered' => $this->participationFee($itemCount, $schedule),
                'per_item' => $itemCount * (float) ($schedule['per_item_amount'] ?? 0),
                'per_student' => $studentCount * (float) ($schedule['per_student_amount'] ?? 0),
                'student_count_slab' => $this->studentCountSlabFee(
                    $this->studentCountSlabBasisCount($schoolId, $schedule, $studentCount),
                    $schedule
                ) + ($studentCount * (float) ($schedule['per_student_amount'] ?? 0)),
                // flat_school isn't part of per-phase billing today — a flat-school event has
                // nothing to split by phase (see plan doc's "Explicitly not changing" note).
                default => 0.0,
            };

            $participationCount = match ($feeModel) {
                'per_student', 'student_count_slab' => $studentCount,
                default => $itemCount,
            };
        }

        $total = round($schoolRegFee + $participationFee, 2);

        $record = FestSchoolEventFee::firstOrNew([
            'event_id' => $event->id,
            'school_id' => $schoolId,
            'phase_id' => $phase->id,
        ]);

        $record->fill(array_filter([
            'school_registration_fee' => $schoolRegFee,
            'student_registration_fee' => $this->supportsSportsCompositeSchema() ? $studentRegFee : null,
            'participation_item_count' => $participationCount,
            'participation_fee' => $participationFee,
            'extra_item_fee' => $this->supportsSportsCompositeSchema() ? $extraItemFee : null,
            'total_due' => $total,
        ], fn ($value) => $value !== null));
        $record->save();

        $record->refreshPaidState();
        $this->applyAvailableCredit($record, $event);

        if ($useComposite && $this->supportsFeeLines()) {
            $this->syncFeeLines($record, $compositeLines);
        } elseif ($this->supportsFeeLines()) {
            $record->lines()->delete();
        }

        return $record;
    }

    /**
     * Recalculate every phase this school has activity under for this event.
     *
     * @return Collection<int, FestSchoolEventFee>
     */
    public function recalculateAllPhasesForSchool(FestEvent $event, string $schoolId): Collection
    {
        return $this->phasesWithActivityForSchool($event, $schoolId)
            ->map(fn (FestEventPhase $phase) => $this->recalculateForPhase($event, $schoolId, $phase))
            ->values();
    }

    /** Is the fee for one specific named Phase fully paid (or not due)? */
    public function isPhasePaid(FestEvent $event, string $schoolId, int $phaseId): bool
    {
        $phase = FestEventPhase::find($phaseId);
        if (! $phase || $phase->event_id !== $event->id) {
            return true;
        }

        $fee = FestSchoolEventFee::where('event_id', $event->id)
            ->where('school_id', $schoolId)
            ->where('phase_id', $phaseId)
            ->first();

        if (! $fee) {
            $fee = $this->recalculateForPhase($event, $schoolId, $phase);
        }

        return $fee->isFullyPaid();
    }

    /** Upload a payment proof against one specific named Phase's fee record. */
    public function attachPaymentForPhase(
        FestEvent $event,
        string $schoolId,
        int $phaseId,
        UploadedFile $proof,
        int $userId,
        ?string $transactionRef = null,
        ?string $bankName = null,
        ?float $amount = null,
        array $extraProofs = [],
    ): FestSchoolEventFee {
        $phase = FestEventPhase::findOrFail($phaseId);
        abort_if($phase->event_id !== $event->id, 403);

        if ($event->usesPhasedRegionalBilling()) {
            abort_if(! $phase->registration_batch_id, 422, 'This phase has no registration payment level.');

            return app(FestRegistrationBatchFeeService::class)->attachPayment(
                $event,
                $schoolId,
                $phase->registration_batch_id,
                $proof,
                $userId,
                $transactionRef,
                $bankName,
                $amount,
                $extraProofs,
            );
        }

        $fee = $this->recalculateForPhase($event, $schoolId, $phase);
        abort_if($fee->total_due <= 0, 422, 'No fee due for this phase.');
        abort_if($fee->isFullyPaid(), 422, 'Fee already fully paid.');

        $outstanding = $fee->outstandingBalance();
        $payAmount = $amount !== null ? round($amount, 2) : $outstanding;
        abort_if($payAmount <= 0, 422, 'Payment amount must be greater than zero.');
        abort_if($payAmount > $outstanding, 422, 'Payment cannot exceed the outstanding balance of ₹'.number_format($outstanding, 2).'.');

        $path = TenantStorage::storeUploadedFile($proof, "fest-payments/{$schoolId}");

        FeeReceipt::supersedePriorForFeeable($fee);

        $receipt = FeeReceipt::create([
            'feeable_type' => FestSchoolEventFee::class,
            'feeable_id' => $fee->id,
            'file_path' => $path,
            'transaction_ref' => $transactionRef,
            'bank_name' => $bankName,
            'payment_date' => now()->toDateString(),
            'amount' => $payAmount,
            'status' => 'uploaded',
            'uploaded_by_user_id' => $userId,
        ]);

        if (! empty($extraProofs)) {
            app(FeeReceiptAttachmentService::class)
                ->attachExtra($receipt, $extraProofs, "fest-payments/{$schoolId}");
        }

        $fee->update([
            'fee_receipt_id' => $receipt->id,
            'status' => 'proof_uploaded',
        ]);

        return $fee->fresh(['feeReceipt']);
    }

    public function recalculate(FestEvent $event, string $schoolId): FestSchoolEventFee
    {
        if ($event->usesPhasedRegionalBilling()) {
            app(FestRegistrationBatchFeeService::class)->recalculateAll($event, $schoolId);

            return FestSchoolEventFee::where('event_id', $event->rootEvent()->id)
                ->where('school_id', $schoolId)
                ->whereNull('registration_batch_id')
                ->whereNull('phase_id')
                ->whereNull('head_id')
                ->firstOrFail();
        }

        // Regional registrations live on a partition child, while the fee
        // schedule and the school's single invoice live on the parent hub.
        if ($event->parent_event_id) {
            $hub = FestEvent::find($event->parent_event_id);
            if ($hub && ($hub->conduct_mode ?? 'standard') === 'partitioned') {
                return $this->recalculate($hub, $schoolId);
            }
        }

        // Unified sports: always bill at event level when fees are on the event (or can dual-read).
        if ($event->event_type === 'sports'
            && ($this->resolveSchedule($event)['fee_model'] ?? 'none') === 'sports_composite'
            && ! $this->usesPerHeadBilling($event)
        ) {
            return $this->recalculateForSportsEvent($event, $schoolId);
        }

        if ($this->usesPerHeadBilling($event)) {
            return $this->recalculateAggregateForPerHeadEvent($event, $schoolId);
        }

        if ($this->usesPerPhaseBilling($event)) {
            return $this->recalculateAggregateForPerPhaseEvent($event, $schoolId);
        }

        $schedule = $this->resolveSchedule($event);
        $school = Tenant::findOrFail($schoolId);
        $itemCount = $this->billableItemCount($event, $schoolId, $schedule);
        $studentCount = $this->billableStudentCount($event, $schoolId);
        $feeModel = $schedule['fee_model'] ?? 'none';

        // A school with no event-level (Step 1) registration and no items registered at
        // all should not be charged the school registration fee. flat_school is exempt —
        // it's a fixed per-event fee not tied to participation by design.
        $hasEventRegistration = FestLevelRegistration::whereIn('event_id', $event->reportableEventIds())
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->exists();
        $hasAnyRegistration = $hasEventRegistration || $itemCount > 0 || $studentCount > 0;

        $schoolRegFee = match ($feeModel) {
            'sports_composite', 'kalolsavam_composite' => $this->sportsCompositeFeeService->schoolRegistrationAmount($school, $schedule),
            'cksc_tiered', 'item_catalog', 'per_student', 'per_item', 'student_count_slab' => $hasAnyRegistration ? $this->schoolRegistrationAmount($school, $schedule) : 0.0,
            default => 0,
        };

        $studentRegFee = 0.0;
        $extraItemFee = 0.0;
        $compositeLines = [];
        $useComposite = in_array($feeModel, ['sports_composite', 'kalolsavam_composite'], true) && $this->supportsSportsCompositeSchema();

        if ($useComposite) {
            $composite = $this->sportsCompositeFeeService->calculate($event, $schoolId, $schedule);
            $schoolRegFee = $composite['school_reg'];
            $studentRegFee = $composite['student_reg'];
            $extraItemFee = $composite['extra_item'];
            $participationFee = $studentRegFee + $extraItemFee;
            $participationCount = $composite['student_count'];
            $compositeLines = $composite['lines'];
        } else {
            if (in_array($feeModel, ['sports_composite', 'kalolsavam_composite'], true)) {
                $feeModel = 'item_catalog';
                $schoolRegFee = $hasAnyRegistration ? $this->schoolRegistrationAmount($school, $schedule) : 0.0;
            }

            $participationFee = match ($feeModel) {
                'cksc_tiered' => $this->participationFee($itemCount, $schedule),
                'item_catalog' => $this->itemFeeResolver->participationTotal($event, $schoolId, $schedule),
                'per_item' => $itemCount * (float) ($schedule['per_item_amount'] ?? 0),
                'flat_school' => (float) ($schedule['flat_amount'] ?? $schedule['fee_amount'] ?? 0),
                'per_student' => $studentCount * (float) ($schedule['per_student_amount'] ?? 0),
                'student_count_slab' => $this->studentCountSlabFee(
                    $this->studentCountSlabBasisCount($schoolId, $schedule, $studentCount),
                    $schedule
                ) + ($studentCount * (float) ($schedule['per_student_amount'] ?? 0)),
                default => 0,
            };

            $participationCount = match ($feeModel) {
                'per_student', 'student_count_slab' => $studentCount,
                default => $itemCount,
            };
        }

        $subtotal = $schoolRegFee + $participationFee;
        $total = $this->applySchoolFeeCap($subtotal, $schedule);
        $total = $this->applySchoolFeeMin($total, $schedule);

        if ($total < $subtotal && $participationFee > 0) {
            $participationFee = max(0, round($total - $schoolRegFee, 2));
        }

        $record = FestSchoolEventFee::firstOrNew([
            'event_id' => $event->id,
            'school_id' => $schoolId,
        ]);

        // Snapshot before overwriting total_due — see demoteSiblingApprovals() for why.
        $wasFullyPaidAndApproved = $record->exists && $record->status === 'approved' && $record->isFullyPaid();

        $record->fill(array_filter([
            'school_registration_fee' => $schoolRegFee,
            'student_registration_fee' => $this->supportsSportsCompositeSchema() ? $studentRegFee : null,
            'participation_item_count' => $participationCount,
            'participation_fee' => $participationFee,
            'extra_item_fee' => $this->supportsSportsCompositeSchema() ? $extraItemFee : null,
            'total_due' => $total,
        ], fn ($value) => $value !== null));
        $record->save();

        // Derive status from the actual receipt state rather than preserving whatever
        // was stored — see recalculateForSportsEvent() for the incident this fixes.
        $record->refreshPaidState();
        $this->applyAvailableCredit($record, $event);

        if ($wasFullyPaidAndApproved && ! $record->isFullyPaid()) {
            $this->demoteSiblingApprovals($event, $schoolId, $record);
        }

        if ($useComposite && $this->supportsFeeLines()) {
            $this->syncFeeLines($record, $compositeLines);
        } elseif ($this->supportsFeeLines()) {
            $record->lines()->delete();
        }

        return $record;
    }

    /**
     * Eagerly recalculate every already-registered school's fee for this event. Used
     * when the fee schedule itself changes (FestEventSettingsController::
     * updateFeeSettings()/updateItemFee()) so already-registered schools see the
     * corrected amount on their next page view instead of carrying a stale total_due
     * until some other registration write event (create/withdraw/approve) happens to
     * refresh it. Mirrors the exact "recalculate eagerly here, it's cheap and
     * idempotent" pattern propagateFeeSettingsToChildren() already uses for partition
     * children — see that method's own comment for the reasoning; this is the same
     * fix applied to the event itself (which propagateFeeSettingsToChildren() never
     * touches — it only cascades to child partitions).
     */
    public function recalculateAllRegisteredSchools(FestEvent $event): void
    {
        FestRegistration::whereIn('event_id', $event->reportableEventIds())
            ->whereIn('status', ['submitted', 'approved', 'pending_approval'])
            ->distinct()
            ->pluck('school_id')
            ->each(fn (string $schoolId) => $this->recalculate($event, $schoolId));
    }

    /**
     * For sports_composite events billed per Event Head, `recalculate()` no longer manages a
     * single payable record — each head has its own fee record, paid independently (see
     * recalculateForHead/attachPaymentForHead/isHeadPaid). This method keeps every per-head
     * record in sync as a side effect, then returns a read-only, head_id=null "rollup" record
     * (total_due = sum of all heads, status reflects whether all heads are settled) purely for
     * legacy callers that still expect a single FestSchoolEventFee back for display purposes
     * (dashboard tiles, reports, invoice generation). This rollup record is NOT itself payable —
     * attachPayment() refuses to accept a proof against it once per-head billing is active.
     */
    private function recalculateAggregateForPerHeadEvent(FestEvent $event, string $schoolId): FestSchoolEventFee
    {
        $headFees = $this->recalculateAllHeadsForSchool($event, $schoolId);

        $totalDue = round((float) $headFees->sum('total_due'), 2);
        $totalPaid = round((float) $headFees->sum('amount_paid'), 2);
        $schoolRegFee = round((float) $headFees->sum('school_registration_fee'), 2);
        $studentRegFee = round((float) $headFees->sum('student_registration_fee'), 2);
        $itemCount = (int) $headFees->sum('participation_item_count');
        $allApproved = $headFees->isNotEmpty() && $headFees->every(fn (FestSchoolEventFee $f) => $f->isFullyPaid());

        $record = FestSchoolEventFee::firstOrNew([
            'event_id' => $event->id,
            'school_id' => $schoolId,
            'head_id' => null,
        ]);

        $record->fill([
            'school_registration_fee' => $schoolRegFee,
            'student_registration_fee' => $this->supportsSportsCompositeSchema() ? $studentRegFee : null,
            'participation_item_count' => $itemCount,
            'participation_fee' => round($totalDue - $schoolRegFee, 2),
            'total_due' => $totalDue,
            'amount_paid' => $totalPaid,
            'status' => $allApproved ? 'approved' : ($totalPaid > 0 ? 'partial' : 'pending'),
        ]);
        $record->save();

        // Deliberately NOT wired into applyAvailableCredit(): this rollup's amount_paid is a
        // manual sum of child per-head records, not driven by this record's own receipts() —
        // calling refreshPaidState() on it (which applyAvailableCredit() needs, to fold a new
        // synthetic credit receipt into amount_paid) would reset amount_paid to just that one
        // receipt and wipe out the real head-level totals summed above. Per-head billing is
        // already legacy/transitional (see usesPerHeadBilling()'s docblock — reachable only for
        // a sports season hub with no discipline children yet), so outstanding FestFeeCredit
        // rows against this head_id=null record are tracked (visible on the credit badge) but
        // not auto-consumed here. See docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §13.2.
        return $record;
    }

    /**
     * Phase-billed equivalent of recalculateAggregateForPerHeadEvent() — for events using
     * usesPerPhaseBilling(), `recalculate()` no longer manages a single payable record; each
     * named Phase has its own fee record, paid independently (see recalculateForPhase/
     * attachPaymentForPhase/isPhasePaid). This keeps every per-phase record in sync as a side
     * effect, then returns a read-only, phase_id=null "rollup" record purely for legacy
     * callers that still expect a single FestSchoolEventFee back for display (dashboard tiles,
     * reports). Not itself payable — same convention as the per-head rollup above.
     */
    private function recalculateAggregateForPerPhaseEvent(FestEvent $event, string $schoolId): FestSchoolEventFee
    {
        $phaseFees = $this->recalculateAllPhasesForSchool($event, $schoolId);

        $totalDue = round((float) $phaseFees->sum('total_due'), 2);
        $totalPaid = round((float) $phaseFees->sum('amount_paid'), 2);
        $schoolRegFee = round((float) $phaseFees->sum('school_registration_fee'), 2);
        $itemCount = (int) $phaseFees->sum('participation_item_count');
        $allApproved = $phaseFees->isNotEmpty() && $phaseFees->every(fn (FestSchoolEventFee $f) => $f->isFullyPaid());

        $record = FestSchoolEventFee::firstOrNew([
            'event_id' => $event->id,
            'school_id' => $schoolId,
            'phase_id' => null,
        ]);

        $record->fill([
            'school_registration_fee' => $schoolRegFee,
            'participation_item_count' => $itemCount,
            'participation_fee' => round($totalDue - $schoolRegFee, 2),
            'total_due' => $totalDue,
            'amount_paid' => $totalPaid,
            'status' => $allApproved ? 'approved' : ($totalPaid > 0 ? 'partial' : 'pending'),
        ]);
        $record->save();

        // Same reasoning as recalculateAggregateForPerHeadEvent()'s rollup: this record's
        // amount_paid is a manual sum of per-phase children, not driven by its own receipts,
        // so it's deliberately left out of applyAvailableCredit()/refreshPaidState().
        return $record;
    }

    /** @param  list<array{line_type: string, label: string, quantity: int, unit_amount: float, amount: float, meta?: array}>  $lines */
    private function syncFeeLines(FestSchoolEventFee $fee, array $lines): void
    {
        $fee->lines()->delete();

        foreach ($lines as $line) {
            FestSchoolEventFeeLine::create([
                'fest_school_event_fee_id' => $fee->id,
                'line_type' => $line['line_type'],
                'label' => $line['label'],
                'quantity' => $line['quantity'] ?? 1,
                'unit_amount' => $line['unit_amount'] ?? $line['amount'],
                'amount' => $line['amount'],
                'meta' => $line['meta'] ?? null,
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function breakdown(FestEvent $event, FestSchoolEventFee $fee, array $schedule): array
    {
        if ($this->supportsFeeLines()) {
            $fee->loadMissing('lines');
        }
        $items = [];
        $feeModel = $schedule['fee_model'] ?? 'none';

        if ($fee->registration_batch_id) {
            foreach ($fee->lines as $line) {
                $items[] = [
                    'label' => $line->label,
                    'amount' => (float) $line->amount,
                    'line_type' => $line->line_type,
                    'quantity' => $line->quantity ?? 1,
                    'meta' => $line->meta,
                ];
            }

            return [
                'items' => $items,
                'total' => (float) $fee->total_due,
                'item_count' => (int) $fee->participation_item_count,
                'registration_batch_id' => (int) $fee->registration_batch_id,
            ];
        }

        if (in_array($feeModel, ['sports_composite', 'kalolsavam_composite'], true)) {
            if ($this->supportsFeeLines()) {
                foreach ($fee->lines as $line) {
                    $items[] = [
                        'label' => $line->label,
                        'amount' => (float) $line->amount,
                        'line_type' => $line->line_type,
                        'quantity' => $line->quantity ?? 1,
                    ];
                }
            }

            if ($items === [] && $fee->total_due > 0) {
                if ($fee->school_registration_fee > 0) {
                    $items[] = ['label' => 'School registration fee ('.$event->title.')', 'amount' => (float) $fee->school_registration_fee, 'line_type' => 'school_reg', 'quantity' => 1];
                }
                if ($this->supportsSportsCompositeSchema() && $fee->student_registration_fee > 0) {
                    $items[] = ['label' => 'Student registration fee ('.$event->title.')', 'amount' => (float) $fee->student_registration_fee, 'line_type' => 'student_reg', 'quantity' => $fee->participation_item_count ?: 1];
                }
                if ($this->supportsSportsCompositeSchema() && $fee->extra_item_fee > 0) {
                    $items[] = ['label' => 'Extra item fees', 'amount' => (float) $fee->extra_item_fee, 'line_type' => 'extra_item', 'quantity' => 1];
                }
            }

            return [
                'items' => $items,
                'total' => (float) $fee->total_due,
                'item_count' => $fee->participation_item_count,
                'student_count' => $fee->participation_item_count,
                'included_quota' => (int) ($schedule['included_items_per_student'] ?? 0),
            ];
        }

        // Every branch below now tags each line with a line_type (and, where the line
        // represents more than one billable unit, a quantity) — previously only
        // sports_composite did this, so the frontend had to guess a line's category by
        // matching its label text. That fragility is exactly what produced the "student
        // registration fee silently double-counted, then silently disappeared" bugs (see
        // itemFeeLines()/studentRegLine() in Registration.vue): item_catalog, cksc_tiered,
        // per_student, and flat_school/per_item never told the frontend what a line WAS,
        // only what it said. line_type is now the single source of truth every fee model
        // provides, and quantity lets the UI show an accurate "(N items)"/"(N students)"
        // count instead of counting lines (one line can represent several billed units —
        // e.g. cksc_tiered's "Additional items" line covers count-1 items in one row).
        if ($feeModel === 'item_catalog' && $fee->participation_item_count > 0) {
            $catalog = $this->itemFeeResolver->participationBreakdown($event, $fee->school_id, $schedule);
            foreach ($catalog['lines'] as $line) {
                $items[] = [
                    'label' => $line['label'],
                    'amount' => (float) $line['amount'],
                    'item_title' => $line['item_title'] ?? null,
                    'head_name' => $line['head_name'] ?? null,
                    'line_type' => 'item_fee',
                    'quantity' => 1,
                ];
            }
        } elseif ($fee->participation_item_count > 0 && $feeModel === 'cksc_tiered') {
            $first = (float) ($schedule['first_item'] ?? 350);
            $additional = (float) ($schedule['additional_item'] ?? 100);
            $count = $fee->participation_item_count;

            if ($count >= 1) {
                $items[] = ['label' => 'First item', 'amount' => $first, 'line_type' => 'item_fee', 'quantity' => 1];
            }
            if ($count > 1) {
                $items[] = [
                    'label' => 'Additional items ('.($count - 1).' × ₹'.$additional.')',
                    'amount' => ($count - 1) * $additional,
                    'line_type' => 'item_fee',
                    'quantity' => $count - 1,
                ];
            }
        } elseif ($feeModel === 'per_student' && $fee->participation_fee > 0) {
            $studentCount = $fee->participation_item_count;
            $rate = (float) ($schedule['per_student_amount'] ?? 0);
            $items[] = [
                'label' => "Participating students ({$studentCount} × ₹{$rate})",
                'amount' => (float) $fee->participation_fee,
                // Not an item_fee — this is the per-student registration charge itself (the
                // same role sports_composite's dedicated student_reg line plays), so the
                // frontend can show it alongside School registration fee instead of folding
                // it into an "Item fees" count where "N items" would misreport N students.
                'line_type' => 'student_reg',
                'quantity' => $studentCount,
            ];
        } elseif ($feeModel === 'student_count_slab' && $fee->participation_fee > 0) {
            $studentCount = $fee->participation_item_count;
            $items[] = [
                'label' => "Student count fee ({$studentCount} student".($studentCount === 1 ? '' : 's').')',
                'amount' => (float) $fee->participation_fee,
                // Same reasoning as the per_student branch above — one stepped charge for
                // the whole school, not N billable items.
                'line_type' => 'student_reg',
                'quantity' => $studentCount,
            ];
        } elseif ($fee->participation_fee > 0) {
            $label = match ($feeModel) {
                'flat_school' => 'Flat school fee',
                'per_item' => 'Participation fees ('.$fee->participation_item_count.' item(s))',
                default => 'Participation fees ('.$fee->participation_item_count.' item(s))',
            };
            $items[] = [
                'label' => $label,
                'amount' => (float) $fee->participation_fee,
                // flat_school is one indivisible charge, not N billable items — kept as its
                // own line_type so the UI never reports it as "(1 item)".
                'line_type' => $feeModel === 'flat_school' ? 'flat_fee' : 'item_fee',
                'quantity' => $feeModel === 'flat_school' ? 1 : $fee->participation_item_count,
            ];
        }

        return [
            'items' => $items,
            'total' => (float) $fee->total_due,
            'item_count' => $fee->participation_item_count,
        ];
    }

    public function attachPayment(
        FestEvent $event,
        string $schoolId,
        UploadedFile $proof,
        int $userId,
        ?string $transactionRef = null,
        ?string $bankName = null,
        ?float $amount = null,
        array $extraProofs = [],
    ): FestSchoolEventFee {
        abort_if(
            $event->usesPhasedRegionalBilling(),
            422,
            'This event bills by registration level — upload payment against Level 1 or Level 2.',
        );

        abort_if(
            $this->usesPerHeadBilling($event),
            422,
            'This event bills fees per Event Head — upload payment against the specific head, not the whole event.',
        );

        abort_if(
            $this->usesPerPhaseBilling($event),
            422,
            'This event bills fees per phase — upload payment against the specific phase, not the whole event.',
        );

        $fee = $this->recalculate($event, $schoolId);
        abort_if($fee->total_due <= 0, 422, 'No fee due for this event.');
        abort_if($fee->isFullyPaid(), 422, 'Fee already fully paid.');

        $outstanding = $fee->outstandingBalance();
        $payAmount = $amount !== null ? round($amount, 2) : $outstanding;
        abort_if($payAmount <= 0, 422, 'Payment amount must be greater than zero.');
        abort_if($payAmount > $outstanding, 422, 'Payment cannot exceed the outstanding balance of ₹'.number_format($outstanding, 2).'.');

        $path = TenantStorage::storeUploadedFile($proof, "fest-payments/{$schoolId}");

        FeeReceipt::supersedePriorForFeeable($fee);

        $receipt = FeeReceipt::create([
            'feeable_type' => FestSchoolEventFee::class,
            'feeable_id' => $fee->id,
            'file_path' => $path,
            'transaction_ref' => $transactionRef,
            'bank_name' => $bankName,
            'payment_date' => now()->toDateString(),
            'amount' => $payAmount,
            'status' => 'uploaded',
            'uploaded_by_user_id' => $userId,
        ]);

        // See attachPaymentForHead() above for why this exists — same additive, no-new-
        // receipt behavior.
        if (! empty($extraProofs)) {
            app(FeeReceiptAttachmentService::class)
                ->attachExtra($receipt, $extraProofs, "fest-payments/{$schoolId}");
        }

        $fee->update([
            'fee_receipt_id' => $receipt->id,
            'status' => 'proof_uploaded',
        ]);

        return $fee->fresh(['feeReceipt']);
    }

    public function isPaid(FestEvent $event, string $schoolId): bool
    {
        if (! $this->feeRequired($event)) {
            return true;
        }

        if ($event->usesPhasedRegionalBilling()) {
            $records = app(FestRegistrationBatchFeeService::class)->recalculateAll($event, $schoolId);

            return $records->isEmpty() || $records->every(fn (FestSchoolEventFee $fee) => $fee->isFullyPaid());
        }

        $fee = FestSchoolEventFee::where('event_id', $this->feeOwnerEvent($event)->id)
            ->where('school_id', $schoolId)
            ->first();

        if (! $fee) {
            $fee = $this->recalculate($event, $schoolId);
        }

        return $fee->isFullyPaid();
    }

    /**
     * The fee-clearance check to use for a specific registration: for per-head events, only that
     * registration's own Event Head needs to be paid (a school can have Athletics cleared while
     * Chess is still pending); for every other event/fee model this is identical to isPaid().
     *
     * Opt-in exception: when an event has strict_item_payment_gating enabled AND uses
     * 'item_catalog'/'per_item' billing (the only models where a per-item price is well
     * defined — see itemPaymentAllocation()), this checks whether THIS item is actually
     * covered by payment instead of the school's aggregate balance. Defaults false on every
     * event, so this branch never runs unless a Sahodaya admin has explicitly turned it on
     * for that one event — every other event keeps the exact behavior below, unchanged.
     * See docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §9.3.
     */
    public function isPaidForRegistration(FestEvent $event, FestRegistration $registration): bool
    {
        if ($event->usesPhasedRegionalBilling()) {
            return app(FestRegistrationBatchFeeService::class)->isPaidForRegistration($event, $registration);
        }

        if (! $this->feeRequired($event)) {
            return true;
        }

        if ($event->strict_item_payment_gating
            && in_array($this->resolveSchedule($event)['fee_model'] ?? null, ['item_catalog', 'per_item'], true)
        ) {
            $allocation = collect($this->itemPaymentAllocation($event, $registration->school_id))
                ->firstWhere('registration_id', $registration->id);

            return $allocation['covered'] ?? false;
        }

        $registration->loadMissing('item');
        $headId = $registration->item?->head_id;

        if ($headId && $this->usesPerHeadBilling($event)) {
            return $this->isHeadPaid($event, $registration->school_id, $headId);
        }

        return $this->isPaid($event, $registration->school_id);
    }

    /**
     * Whether an approved payment already exists against the fee record covering this
     * registration (the specific Event Head's record for per-head billing, the single
     * event-wide record otherwise). Used to lock cancellation — per the confirmed product
     * rule, a registration may only be cancelled pre-payment-approval; once any amount has
     * been approved against its fee record, cancellation is no longer allowed.
     */
    public function hasApprovedPaymentForRegistration(FestEvent $event, FestRegistration $registration): bool
    {
        if ($event->usesPhasedRegionalBilling()) {
            $batch = app(FestRegistrationBatchFeeService::class)->batchForRegistration($event, $registration);
            if (! $batch) {
                return false;
            }

            return FestSchoolEventFee::where('event_id', $event->rootEvent()->id)
                ->where('school_id', $registration->school_id)
                ->where('registration_batch_id', $batch->id)
                ->where('amount_paid', '>', 0)
                ->exists();
        }

        $registration->loadMissing('item');
        $headId = $registration->item?->head_id;

        $query = FestSchoolEventFee::where('event_id', $this->feeOwnerEvent($event)->id)
            ->where('school_id', $registration->school_id);

        if ($headId && $this->usesPerHeadBilling($event)) {
            $query->where('head_id', $headId);
        } else {
            $query->whereNull('head_id');
        }

        $fee = $query->first();

        return $fee && (float) $fee->amount_paid > 0;
    }

    /**
     * The event-wide fee record for a school — a read-only lookup, does NOT create one if
     * missing (unlike recalculate()). Deliberately always resolves head_id = null: every
     * recalculate() dispatch branch (plain, sports-event, and the per-head "rollup" via
     * recalculateAggregateForPerHeadEvent) returns a head_id = null record, so this must match
     * that exact record to produce a meaningful before/after comparison — a specific Event
     * Head's row is a different total_due than the event-wide rollup recalculate() returns.
     * Used to snapshot total_due/amount_paid before a registration is rejected, so the caller
     * can measure the effect of the rejection without needing to know how any particular fee
     * model prices an individual item. See docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §9.2.
     */
    public function currentFeeRecordFor(FestEvent $event, string $schoolId): ?FestSchoolEventFee
    {
        return FestSchoolEventFee::where('event_id', $this->feeOwnerEvent($event)->id)
            ->where('school_id', $schoolId)
            ->whereNull('head_id')
            ->when($event->usesPhasedRegionalBilling(), fn ($query) => $query->whereNull('registration_batch_id'))
            ->first();
    }

    /**
     * Mark outstanding FestFeeCredit rows as consumed, whole-row only (never splits a row —
     * if the next row would push past $amount, it's left outstanding for a future fee), up to
     * $amount. Deliberately does NOT touch amount_paid/total_due/status — see
     * FestSchoolEventFee::effectiveOutstandingBalance() for why folding credit into
     * amount_paid directly is unsafe (refreshPaidState() recomputes it from approved receipts
     * every time and would silently discard it). Callers that actually settle a balance using
     * credit (i.e. FestSchoolEventFeeController::forceApprove(), the system's existing,
     * already-correct "mark paid without new receipt money" action) call this alongside their
     * own logic so the credit ledger and the real payment state stay in sync.
     *
     * Also posts the "credit consumed" ledger leg (FestFeeLedgerService::postCreditConsumed())
     * once per row actually marked applied_at here — centralized in this one method rather
     * than left to each caller, so BOTH consumption paths (forceApprove()'s manual waiver and
     * applyAvailableCredit()'s automatic offset) post consistently instead of only whichever
     * one remembered to call it. Posted per-row (not as one lump sum for the whole $amount)
     * so each posting is referenced by, and idempotent per, that credit row's own id — see
     * FestFeeCredit::CONSUMPTION_REFERENCE.
     * See docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §13.
     */
    public function markCreditsApplied(FestSchoolEventFee $fee, float $amount): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        $fee->loadMissing('event');
        $consumingEvent = $fee->event;

        $applied = 0.0;
        foreach ($fee->credits()->outstanding()->oldest()->get() as $credit) {
            $creditAmount = (float) $credit->amount;
            if ($applied + $creditAmount > $amount) {
                break;
            }
            $credit->update(['applied_at' => now()]);
            $applied += $creditAmount;

            if ($consumingEvent) {
                app(FestFeeLedgerService::class)->postCreditConsumed($credit, $consumingEvent, $creditAmount);
            }
        }

        return round($applied, 2);
    }

    /**
     * Auto-apply any outstanding, unapplied FestFeeCredit against this fee record's unpaid
     * balance — additive, zero effect for the common case (no credit, or nothing outstanding).
     * Scoped to head_id = null records only, matching where rejectMany()/cancelWithRefund()
     * actually create FestFeeCredit rows (see currentFeeRecordFor()'s docblock) — a no-op for
     * per-head-billed records.
     *
     * Deliberately does NOT fold the credit into total_due or write amount_paid directly —
     * refreshPaidState() recomputes amount_paid from scratch as sum(approved FeeReceipt) every
     * time it runs (see TracksPartialPayments), so anything bypassing that would be silently
     * discarded on the very next recalculate(). Instead this creates a system-generated,
     * already-approved FeeReceipt (is_system_credit = true, no real proof file — see
     * FeeReceipt::isSystemCredit()) for the amount actually covered, so refreshPaidState()'s
     * own math picks it up exactly like a real payment. This makes it idempotent: once created,
     * the receipt persists and the underlying FestFeeCredit rows are marked applied_at in the
     * same transaction, so repeated recalculate() calls never double-apply the same credit —
     * unlike netting credit into total_due directly, which would have reset back to the gross
     * amount on the very next call once the credit rows were marked consumed.
     *
     * Locks the fee row for the duration of the check-and-apply so two concurrent
     * recalculate() calls for the same school can't both see the same outstanding credit and
     * apply it twice (see markCreditsApplied()'s oldest-first, whole-row-only consumption).
     *
     * See docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §13.2/§13.3.
     */
    private function applyAvailableCredit(FestSchoolEventFee $record, FestEvent $event): void
    {
        if ($record->head_id !== null || ! $record->exists) {
            return;
        }

        if ($record->outstandingBalance() <= 0 || $record->outstandingCredit() <= 0) {
            return;
        }

        $receipt = DB::transaction(function () use ($record) {
            $locked = FestSchoolEventFee::whereKey($record->id)->lockForUpdate()->first();
            if (! $locked) {
                return null;
            }

            $creditCap = round(min($locked->outstandingBalance(), $locked->outstandingCredit()), 2);
            if ($creditCap <= 0) {
                return null;
            }

            // markCreditsApplied() only ever consumes WHOLE credit rows that individually
            // fit under the requested cap (see its own docblock) — it can legitimately mark
            // less than $creditCap as applied (e.g. a single outstanding row bigger than the
            // cap is skipped entirely, left for a future, larger balance). Calling it FIRST
            // and using its *return value* — not $creditCap — as the receipt/ledger amount is
            // essential: creating the receipt for $creditCap while only $applied worth of
            // FestFeeCredit rows actually got marked applied_at would let the unconsumed
            // remainder be "spent" again on the very next recalculate(), fabricating money
            // and double-applying the same credit.
            $applied = $this->markCreditsApplied($locked, $creditCap);
            if ($applied <= 0) {
                return null;
            }

            $receipt = FeeReceipt::create([
                'feeable_type' => FestSchoolEventFee::class,
                'feeable_id' => $locked->id,
                'file_path' => 'system://fee-credit-adjustment',
                'transaction_ref' => 'CREDIT-OFFSET',
                'bank_name' => 'Fee Credit Adjustment',
                'payment_date' => now()->toDateString(),
                'amount' => $applied,
                'status' => 'approved',
                'is_system_credit' => true,
                'reviewed_at' => now(),
            ]);

            if (! $locked->fee_receipt_id) {
                $locked->update(['fee_receipt_id' => $receipt->id]);
            }

            $locked->refreshPaidState();

            return $receipt;
        });

        // Ledger posting for the consumed credit already happened inside
        // markCreditsApplied() above, per-row — nothing further to post here.
        if ($receipt) {
            $record->refresh();
        }
    }

    /**
     * When a school adds a new item after the rest of their registrations for this
     * event/head were already approved-and-fully-paid, and the resulting total_due now
     * exceeds amount_paid again, demote every other 'approved' registration for that
     * school+event back to 'submitted' — "approved" should always mean "currently backed
     * by payment," not "was paid once, before something else got added." Deliberately
     * does NOT touch chest numbers or marks (unlike cancelWithRefund()) — this is a
     * payment-status reversal, not a withdrawal; the registration itself is untouched and
     * simply needs the fee settled again before FestRegistrationApprovalService::
     * approveSchoolEvent() re-approves it. Scoped to head_id = null callers only (the two
     * non-per-head recalculate() paths) — per-head billing is legacy/rare (see
     * usesPerHeadBilling()'s docblock) and out of scope here, same as
     * recalculateAggregateForPerHeadEvent().
     *
     * Product decision confirmed 24 Jul 2026: always demote (no exception for results
     * already published) — see docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §5.
     */
    private function demoteSiblingApprovals(FestEvent $event, string $schoolId, FestSchoolEventFee $fee): void
    {
        $registrations = FestRegistration::whereIn('event_id', $event->reportableEventIds())
            ->where('school_id', $schoolId)
            ->where('status', 'approved')
            ->get(['id']);

        if ($registrations->isEmpty()) {
            return;
        }

        FestRegistration::whereIn('id', $registrations->pluck('id'))
            ->update(['status' => 'submitted']);

        app(PlatformAuditLogger::class)->log(
            action: 'fest.registration.demoted_unpaid',
            description: "{$registrations->count()} approved registration(s) demoted back to submitted — school's balance for \"{$event->title}\" is unpaid again after new items were added",
            subject: $fee,
            properties: [
                'event_id' => $event->id,
                'school_id' => $schoolId,
                'registration_ids' => $registrations->pluck('id')->all(),
                'total_due' => (float) $fee->total_due,
                'amount_paid' => (float) $fee->amount_paid,
            ],
            category: 'finance',
        );
    }

    /**
     * Per-item payment coverage for a school's active (submitted/approved) registrations on
     * this event — ONLY meaningful for 'item_catalog'/'per_item' billing, where each item has
     * its own fee_amount and a per-item price genuinely exists. For every other fee model
     * (cksc_tiered, flat_school, per_student, sports_composite) cost is not attributable to a
     * single item, so this deliberately returns an empty array rather than a misleading number.
     *
     * Allocation is "first paid, first covered": registrations ordered oldest-submitted-first,
     * walking the fee record's amount_paid down that list.
     *
     * Read-only — used for display (the school-page item checklist, admin reports) and, only
     * when an event has strict_item_payment_gating explicitly enabled, by isPaidForRegistration().
     * Never called from the four existing approval call sites otherwise.
     *
     * @return array<int, array{registration_id: int, item_id: ?int, item_title: ?string, amount: float, covered: bool}>
     */
    public function itemPaymentAllocation(FestEvent $event, string $schoolId): array
    {
        $schedule = $this->resolveSchedule($event);
        $feeModel = $schedule['fee_model'] ?? 'none';

        if (! in_array($feeModel, ['item_catalog', 'per_item'], true)) {
            return [];
        }

        $fee = FestSchoolEventFee::where('event_id', $this->feeOwnerEvent($event)->id)
            ->where('school_id', $schoolId)
            ->whereNull('head_id')
            ->first();

        $paidRemaining = (float) ($fee?->amount_paid ?? 0);

        $registrations = FestRegistration::whereIn('event_id', $event->reportableEventIds())
            ->where('school_id', $schoolId)
            ->whereIn('status', ['submitted', 'approved', 'pending_approval'])
            ->with('item')
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();

        $perItemAmount = (float) ($schedule['per_item_amount'] ?? 0);

        $rows = [];
        foreach ($registrations as $registration) {
            // BUG FIX: item_catalog pricing has a fallback chain (item override → competition
            // area → head default → participant-type/age-group/class-group rates → schedule
            // default) — see FestItemFeeResolver::amountForItem(), the exact method
            // recalculate() uses via participationTotal(). An earlier version of this method
            // read $registration->item->fee_amount directly, which is only the FIRST link in
            // that chain — any item priced via area/head/class-group defaults (the common case,
            // not the exception) would show as ₹0 here and always read as "covered", silently
            // diverging from the real total_due and, if strict_item_payment_gating is ever
            // turned on for such an event, wrongly approving unpaid items.
            $amount = $feeModel === 'per_item'
                ? $perItemAmount
                : $this->itemFeeResolver->amountForItem($registration->item, $schedule, $event, registration: $registration);

            $covered = $amount <= 0 || $paidRemaining >= $amount;
            if ($covered) {
                $paidRemaining = max(0, $paidRemaining - $amount);
            }

            $rows[] = [
                'registration_id' => $registration->id,
                'item_id' => $registration->item_id,
                'item_title' => $registration->item?->title,
                'amount' => round($amount, 2),
                'covered' => $covered,
            ];
        }

        return $rows;
    }

    private function applySchoolFeeCap(float $total, array $schedule): float
    {
        $cap = isset($schedule['school_fee_cap']) ? (float) $schedule['school_fee_cap'] : null;

        if ($cap !== null && $cap > 0 && $total > $cap) {
            return $cap;
        }

        return $total;
    }

    private function applySchoolFeeMin(float $total, array $schedule): float
    {
        $min = isset($schedule['school_fee_min']) ? (float) $schedule['school_fee_min'] : null;

        if ($min !== null && $min > 0 && $total > 0 && $total < $min) {
            return $min;
        }

        return $total;
    }

    private function supportsSportsCompositeSchema(): bool
    {
        return Schema::hasColumn('fest_school_event_fees', 'student_registration_fee')
            && Schema::hasColumn('fest_school_event_fees', 'extra_item_fee');
    }

    private function supportsFeeLines(): bool
    {
        return Schema::hasTable('fest_school_event_fee_lines');
    }
}
