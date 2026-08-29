<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestItemHead;
use App\Models\FestLevelRegistration;
use App\Models\FestRegistration;
use App\Models\FestSchoolFeeSlabSelection;
use App\Models\Tenant;
use App\Support\SchoolClassCategoryResolver;

class FestSportsCompositeFeeService
{
    public function __construct(
        private FestItemFeeResolver $itemFeeResolver,
    ) {}

    /**
     * Per-sport-event composite billing (Head = Event unification).
     *
     * Reads fee columns from FestEvent first; when those are empty, falls back to the
     * linked FestItemHead (source_head_id or sole head on the event) so migration can
     * land before fest:migrate-sports-head-to-event has been run.
     *
     * Billing rules (same as former per-head model, scoped to the whole sport event):
     *  - School Registration Fee: once per school per sport event
     *  - Student Registration Fee: once per student with ≥1 individual item
     *  - Per individual item: default_item_fee (or student_registration_fee), with free quota
     *  - Team items: team_registration_fee once per team, with team quota
     *
     * @return array{
     *   school_reg: float,
     *   student_reg: float,
     *   student_count: int,
     *   item_fee: float,
     *   team_fee: float,
     *   included_quota: int,
     *   included_teams: int,
     *   lines: list<array{line_type: string, label: string, quantity: int, unit_amount: float, amount: float, meta?: array}>
     * }
     */
    public function calculateForEvent(FestEvent $event, string $schoolId): array
    {
        $fees = $this->resolveSportsFeeSource($event);

        // Phase L: group_item_flat_fee/group_item_per_participant_rate and charge_standbys
        // live in fee_settings (JSON) same as every other Kalotsavam schedule key — sports
        // composite's own dedicated fee columns (read via resolveSportsFeeSource() above)
        // never grew a JSON column, so this is read straight off the event rather than
        // threaded through $fees. See FestItemFeeResolver::groupItemSurchargeAmount().
        $groupFeeSchedule = $event->fee_settings ?? [];

        $schoolReg = (float) ($fees['school_registration_fee'] ?? 0);
        $studentRegRate = (float) ($fees['student_registration_fee'] ?? 0);
        $teamRegRate = (float) ($fees['team_registration_fee'] ?? 0);
        $individualQuota = max(0, (int) ($fees['included_items_per_student'] ?? 0));
        $teamQuota = max(0, (int) ($fees['included_teams'] ?? 0));
        $defaultItemFee = $fees['default_item_fee'] ?? null;
        $extraItemFee = $fees['extra_item_fee'] ?? null;
        $eventTitle = $event->title ?: 'Sport event';

        $registrations = FestRegistration::whereIn('event_id', $event->reportableEventIds())
            ->where('school_id', $schoolId)
            ->whereIn('status', ['submitted', 'approved', 'pending_approval'])
            // A withdrawn/disabled/deleted item should never keep billing a school —
            // whoever turned the item off is telling us it's no longer offered.
            ->whereHas('item', fn ($q) => $q->where('is_enabled', true))
            ->with(['item', 'participants'])
            ->orderBy('id')
            ->get();

        $lines = [];
        $studentsBilledBase = [];

        // Event-level registration (Step 1: Event Athletes) alone does not incur the
        // per-student registration fee — only students who go on to register for at
        // least one item (Step 2) are billed. Track event-athlete presence separately
        // so the school registration fee below can still tell "nothing registered at
        // all" apart from "registered for the event, but no items yet".
        $hasEventRegistration = FestLevelRegistration::where('event_id', $event->id)
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->exists();

        // Students billed for the per-student registration fee: only those with at
        // least one actual item registration (individual or team).
        foreach ($registrations as $registration) {
            foreach ($registration->participants as $participant) {
                if ($participant->participant_role !== 'standby' && $participant->student_id) {
                    $studentsBilledBase[$participant->student_id] = true;
                }
            }
        }

        $individualQuotaUsed = [];
        $itemFeeTotal = 0.0;

        foreach ($registrations as $registration) {
            if ($registration->item?->isTeamItem()) {
                continue;
            }

            foreach ($registration->participants as $participant) {
                if ($participant->participant_role === 'standby' || ! $participant->student_id) {
                    continue;
                }

                $studentId = $participant->student_id;
                $studentsBilledBase[$studentId] = true;

                $used = $individualQuotaUsed[$studentId] ?? 0;
                $eligible = (bool) ($registration->item->quota_eligible ?? false);
                $waived = $eligible && $used < $individualQuota;

                // A per-item fee override (set on the event's Items/Fees page) always
                // wins over the flat event-wide rate — that's the whole point of an
                // override. Quota waivers still apply on top of it.
                $itemOverride = $registration->item->fee_amount !== null ? (float) $registration->item->fee_amount : null;

                if ($waived) {
                    $individualQuotaUsed[$studentId] = $used + 1;
                    $amount = 0.0;
                } elseif ($itemOverride !== null) {
                    $amount = $itemOverride;
                } else {
                    $amount = (float) ($defaultItemFee ?? $studentRegRate);
                    if ($eligible && $individualQuota > 0 && $extraItemFee !== null) {
                        $amount = (float) $extraItemFee;
                    }
                }

                $itemTitle = str_replace('_', ' ', $registration->item->title ?? 'Item');
                $lines[] = [
                    'line_type' => $waived ? 'item_fee_waived' : 'item_fee',
                    'label' => $itemTitle.($waived ? ' (free quota)' : ''),
                    'quantity' => 1,
                    'unit_amount' => $amount,
                    'amount' => $amount,
                    'meta' => [
                        'student_id' => $studentId,
                        'item_id' => $registration->item_id,
                        'registration_id' => $registration->id,
                        'event_id' => $event->id,
                    ],
                ];
                $itemFeeTotal += $amount;
            }
        }

        $teamQuotaUsed = 0;
        $teamFeeTotal = 0.0;

        foreach ($registrations as $registration) {
            if (! $registration->item?->isTeamItem()) {
                continue;
            }

            $performersCount = $registration->participants
                ->filter(fn ($p) => $p->participant_role !== 'standby' && $p->student_id)
                ->count();

            if ($performersCount === 0) {
                continue;
            }

            $eligible = (bool) ($registration->item->quota_eligible ?? false);
            $waived = $eligible && $teamQuotaUsed < $teamQuota;
            $itemOverride = $registration->item->fee_amount !== null ? (float) $registration->item->fee_amount : null;
            $itemTitle = str_replace('_', ' ', $registration->item->title ?? 'Team item');

            if ($waived) {
                $teamQuotaUsed++;
                $amount = 0.0;
                $itemLineLabel = $itemTitle.' — team fee (free quota)';
                $quantity = 1;
                $unitAmount = 0.0;
            } elseif ($itemOverride !== null) {
                $amount = $itemOverride;
                $itemLineLabel = $itemTitle.' — team fee (override)';
                $quantity = 1;
                $unitAmount = $itemOverride;
            } elseif (($groupFee = $this->itemFeeResolver->groupItemSurchargeAmount($registration->item, $groupFeeSchedule, $registration)) !== null) {
                // Phase L: flat event fee + per-participant surcharge, opt-in via
                // group_item_flat_fee/group_item_per_participant_rate (item override, else
                // event-wide schedule default) — see groupItemSurchargeAmount() doc comment.
                // Reuses the exact same computation FestItemFeeResolver::amountForItem() uses
                // for Kalotsavam group items, so both places agree on how a 7-member group
                // item bills ₹250 + (₹100×7).
                $amount = $groupFee;
                $itemLineLabel = $itemTitle.' — team fee (flat + per-participant)';
                $quantity = $performersCount;
                $unitAmount = $performersCount > 0 ? round($groupFee / $performersCount, 2) : $groupFee;
            } else {
                if ($teamRegRate == 0) {
                    $itemFee = (float) ($defaultItemFee ?? $studentRegRate);
                    $amount = $itemFee * $performersCount;
                    $itemLineLabel = $itemTitle." ({$performersCount} × ₹".number_format($itemFee, 0).')';
                    $quantity = $performersCount;
                    $unitAmount = $itemFee;
                } else {
                    $amount = $teamRegRate;
                    $itemLineLabel = $itemTitle.' — team fee';
                    $quantity = 1;
                    $unitAmount = $teamRegRate;
                }
            }

            $lines[] = [
                'line_type' => $waived ? 'team_fee_waived' : 'team_fee',
                'label' => $itemLineLabel,
                'quantity' => $quantity,
                'unit_amount' => $unitAmount,
                'amount' => $amount,
                'meta' => [
                    'registration_id' => $registration->id,
                    'item_id' => $registration->item_id,
                    'event_id' => $event->id,
                ],
            ];
            $teamFeeTotal += $amount;
        }

        $studentCount = count($studentsBilledBase);
        $studentRegTotal = round($studentCount * $studentRegRate, 2);

        // No school registration fee if the school hasn't registered anything at all
        // for this event — neither an event-level (Step 1) registration nor any item.
        $hasAnyParticipation = $hasEventRegistration || $studentCount > 0 || $registrations->isNotEmpty();
        if (! $hasAnyParticipation) {
            $schoolReg = 0.0;
        }

        $summaryLines = [];
        if ($schoolReg > 0) {
            $summaryLines[] = [
                'line_type' => 'school_reg',
                'label' => 'School registration fee ('.$eventTitle.')',
                'quantity' => 1,
                'unit_amount' => $schoolReg,
                'amount' => $schoolReg,
                'meta' => ['event_id' => $event->id],
            ];
        }
        if ($studentRegTotal > 0) {
            $summaryLines[] = [
                'line_type' => 'student_reg',
                'label' => "Student registration ({$eventTitle}) — {$studentCount} × ₹".number_format($studentRegRate, 0),
                'quantity' => $studentCount,
                'unit_amount' => $studentRegRate,
                'amount' => $studentRegTotal,
                'meta' => ['event_id' => $event->id],
            ];
        }

        return [
            'school_reg' => $schoolReg,
            'student_reg' => $studentRegTotal,
            'student_count' => $studentCount,
            'item_fee' => round($itemFeeTotal, 2),
            'team_fee' => round($teamFeeTotal, 2),
            'included_quota' => $individualQuota,
            'included_teams' => $teamQuota,
            'lines' => array_merge($summaryLines, $lines),
        ];
    }

    /**
     * Dual-read: FestEvent columns first, then linked FestItemHead fallback.
     *
     * @return array{
     *   school_registration_fee: mixed,
     *   student_registration_fee: mixed,
     *   team_registration_fee: mixed,
     *   included_items_per_student: mixed,
     *   included_teams: mixed,
     *   default_item_fee: mixed,
     *   extra_item_fee: mixed
     * }
     */
    public function resolveSportsFeeSource(FestEvent $event): array
    {
        if ($event->hasSportsFeesConfigured()) {
            return [
                'school_registration_fee' => $event->school_registration_fee,
                'student_registration_fee' => $event->student_registration_fee,
                'team_registration_fee' => $event->team_registration_fee,
                'included_items_per_student' => $event->included_items_per_student,
                'included_teams' => $event->included_teams,
                'default_item_fee' => $event->default_item_fee,
                'extra_item_fee' => $event->extra_item_fee,
            ];
        }

        $head = null;
        if ($event->source_head_id) {
            $head = FestItemHead::find($event->source_head_id);
        }
        if (! $head) {
            $head = FestItemHead::where('event_id', $event->id)->whereNull('parent_id')->orderBy('sort_order')->first();
        }

        if ($head) {
            return [
                'school_registration_fee' => $head->school_registration_fee,
                'student_registration_fee' => $head->student_registration_fee,
                'team_registration_fee' => $head->team_registration_fee,
                'included_items_per_student' => $head->included_items_per_student,
                'included_teams' => $head->included_teams,
                'default_item_fee' => $head->default_item_fee,
                'extra_item_fee' => $head->extra_item_fee,
            ];
        }

        // Nothing configured on the event itself and no linked legacy head — fall
        // back to the sahodaya-wide sports fee defaults (config('fest_fees.level_defaults.sports')),
        // same as resolveSchedule() does for display. Without this, a standalone
        // sport event that was never opened on Settings → Fee settings silently
        // billed everyone ₹0 while the Fees page schedule still showed the
        // platform defaults as "active" — the actual charge and the displayed
        // schedule disagreed.
        $defaults = config('fest_fees.level_defaults.sports', []);

        return [
            'school_registration_fee' => $defaults['school_registration_flat'] ?? null,
            'student_registration_fee' => $defaults['per_student_amount'] ?? null,
            'team_registration_fee' => $defaults['team_registration_fee'] ?? null,
            'included_items_per_student' => $defaults['included_items_per_student'] ?? 0,
            'included_teams' => $defaults['included_teams'] ?? 0,
            'default_item_fee' => $defaults['default_item_fee'] ?? null,
            'extra_item_fee' => $defaults['extra_item_fee'] ?? null,
        ];
    }

    /**
     * Per-Event-Head composite billing (legacy — kept for Kalotsav / unmigrated rows).
     * Prefer calculateForEvent() for sports after Head = Event unification.
     *
     * @return array{
     *   school_reg: float,
     *   student_reg: float,
     *   student_count: int,
     *   item_fee: float,
     *   team_fee: float,
     *   included_quota: int,
     *   included_teams: int,
     *   lines: list<array{line_type: string, label: string, quantity: int, unit_amount: float, amount: float, meta?: array}>
     * }
     */
    public function calculateForHead(FestItemHead $head, string $schoolId): array
    {
        // If the head's event already has unified fee columns, bill at event level.
        $event = $head->event;
        if ($event && $event->event_type === 'sports' && $event->hasSportsFeesConfigured()) {
            return $this->calculateForEvent($event, $schoolId);
        }

        $schoolReg = (float) ($head->school_registration_fee ?? 0);
        $studentRegRate = (float) ($head->student_registration_fee ?? 0);
        $teamRegRate = (float) ($head->team_registration_fee ?? 0);
        $individualQuota = max(0, (int) ($head->included_items_per_student ?? 0));
        $teamQuota = max(0, (int) ($head->included_teams ?? 0));

        // Phase L — see the identical comment in calculateForEvent().
        $groupFeeSchedule = $event?->fee_settings ?? [];

        $registrations = FestRegistration::whereIn('event_id', $event ? $event->reportableEventIds() : [$head->event_id])
            ->where('school_id', $schoolId)
            ->whereIn('status', ['submitted', 'approved', 'pending_approval'])
            ->whereHas('item', fn ($q) => $q->where('head_id', $head->id)->where('is_enabled', true))
            ->with(['item', 'participants'])
            ->orderBy('id')
            ->get();

        $lines = [];
        $studentsBilledBase = [];
        $individualQuotaUsed = [];
        $itemFeeTotal = 0.0;

        foreach ($registrations as $registration) {
            if ($registration->item?->isTeamItem()) {
                continue;
            }

            foreach ($registration->participants as $participant) {
                if ($participant->participant_role === 'standby' || ! $participant->student_id) {
                    continue;
                }

                $studentId = $participant->student_id;
                $studentsBilledBase[$studentId] = true;

                $used = $individualQuotaUsed[$studentId] ?? 0;
                $eligible = (bool) ($registration->item->quota_eligible ?? false);
                $waived = $eligible && $used < $individualQuota;

                // Per-item override (event Items/Fees page) takes priority over the
                // head's flat rate.
                $itemOverride = $registration->item->fee_amount !== null ? (float) $registration->item->fee_amount : null;

                if ($waived) {
                    $individualQuotaUsed[$studentId] = $used + 1;
                    $amount = 0.0;
                } elseif ($itemOverride !== null) {
                    $amount = $itemOverride;
                } else {
                    $amount = (float) ($head->default_item_fee ?? $studentRegRate);
                    if ($eligible && $individualQuota > 0 && $head->extra_item_fee !== null) {
                        // Beyond free quota: prefer explicit extra rate when configured.
                        $amount = (float) $head->extra_item_fee;
                    }
                }

                $lines[] = [
                    'line_type' => $waived ? 'item_fee_waived' : 'item_fee',
                    'label' => ($registration->item->title ?? 'Item').($waived ? ' (free quota)' : ''),
                    'quantity' => 1,
                    'unit_amount' => $amount,
                    'amount' => $amount,
                    'meta' => [
                        'student_id' => $studentId,
                        'item_id' => $registration->item_id,
                        'registration_id' => $registration->id,
                        'head_id' => $head->id,
                        'head_name' => $head->name,
                    ],
                ];
                $itemFeeTotal += $amount;
            }
        }

        $teamQuotaUsed = 0;
        $teamFeeTotal = 0.0;

        foreach ($registrations as $registration) {
            if (! $registration->item?->isTeamItem()) {
                continue;
            }

            $performersCount = $registration->participants
                ->filter(fn ($p) => $p->participant_role !== 'standby' && $p->student_id)
                ->count();

            if ($performersCount === 0) {
                continue;
            }

            $eligible = (bool) ($registration->item->quota_eligible ?? false);
            $waived = $eligible && $teamQuotaUsed < $teamQuota;
            $itemOverride = $registration->item->fee_amount !== null ? (float) $registration->item->fee_amount : null;
            $itemTitle = str_replace('_', ' ', $registration->item->title ?? 'Team item');

            if ($waived) {
                $teamQuotaUsed++;
                $amount = 0.0;
                $label = $itemTitle.' — team fee (free quota)';
                $quantity = 1;
                $unitAmount = 0.0;
            } elseif ($itemOverride !== null) {
                $amount = $itemOverride;
                $label = $itemTitle.' — team fee (override)';
                $quantity = 1;
                $unitAmount = $itemOverride;
            } elseif (($groupFee = $this->itemFeeResolver->groupItemSurchargeAmount($registration->item, $groupFeeSchedule, $registration)) !== null) {
                // Phase L — see the identical branch in calculateForEvent().
                $amount = $groupFee;
                $label = $itemTitle.' — team fee (flat + per-participant)';
                $quantity = $performersCount;
                $unitAmount = $performersCount > 0 ? round($groupFee / $performersCount, 2) : $groupFee;
            } else {
                if ($teamRegRate == 0) {
                    $itemFee = (float) ($head->default_item_fee ?? $studentRegRate);
                    $amount = $itemFee * $performersCount;
                    $label = $itemTitle." ({$performersCount} × ₹".number_format($itemFee, 0).')';
                    $quantity = $performersCount;
                    $unitAmount = $itemFee;
                } else {
                    $amount = $teamRegRate;
                    $label = $itemTitle.' — team fee';
                    $quantity = 1;
                    $unitAmount = $teamRegRate;
                }
            }

            $lines[] = [
                'line_type' => $waived ? 'team_fee_waived' : 'team_fee',
                'label' => $label,
                'quantity' => $quantity,
                'unit_amount' => $unitAmount,
                'amount' => $amount,
                'meta' => [
                    'registration_id' => $registration->id,
                    'item_id' => $registration->item_id,
                    'head_id' => $head->id,
                    'head_name' => $head->name,
                ],
            ];
            $teamFeeTotal += $amount;
        }

        $studentCount = count($studentsBilledBase);
        $studentRegTotal = round($studentCount * $studentRegRate, 2);

        // No school registration fee if nothing was actually registered under this head.
        if (! ($studentCount > 0 || $registrations->isNotEmpty())) {
            $schoolReg = 0.0;
        }

        $summaryLines = [];
        if ($schoolReg > 0) {
            $summaryLines[] = [
                'line_type' => 'school_reg',
                'label' => 'School registration fee ('.$head->name.')',
                'quantity' => 1,
                'unit_amount' => $schoolReg,
                'amount' => $schoolReg,
                'meta' => ['head_id' => $head->id, 'head_name' => $head->name],
            ];
        }
        if ($studentRegTotal > 0) {
            $summaryLines[] = [
                'line_type' => 'student_reg',
                'label' => "Student registration ({$head->name}) — {$studentCount} × ₹".number_format($studentRegRate, 0),
                'quantity' => $studentCount,
                'unit_amount' => $studentRegRate,
                'amount' => $studentRegTotal,
                'meta' => ['head_id' => $head->id, 'head_name' => $head->name],
            ];
        }

        return [
            'school_reg' => $schoolReg,
            'student_reg' => $studentRegTotal,
            'student_count' => $studentCount,
            'item_fee' => round($itemFeeTotal, 2),
            'team_fee' => round($teamFeeTotal, 2),
            'included_quota' => $individualQuota,
            'included_teams' => $teamQuota,
            'lines' => array_merge($summaryLines, $lines),
        ];
    }

    /**
     * @return array{
     *   school_reg: float,
     *   student_reg: float,
     *   student_count: int,
     *   extra_item: float,
     *   included_quota: int,
     *   lines: list<array{line_type: string, label: string, quantity: int, unit_amount: float, amount: float, meta?: array}>,
     *   phase_attribution: array{
     *     per_student_rate: float,
     *     student_reg: array{by_phase: array<int, array{amount: float, student_count: int}>, no_phase: array{amount: float, student_count: int}, unattributed: array{amount: float, student_count: int}},
     *     extra_item: array{by_phase: array<int, float>, no_phase: float}
     *   }
     * }
     *
     * The whole method stays event-wide and unfiltered — quota/position is computed once
     * across every registration for the event, never reset per phase. phase_attribution is
     * additive, derived from that same walk: it's what FestSchoolEventFeeService::
     * recalculateForPhase() reads to split this single, correctly-quota'd calculation across
     * each FestEventPhase's own invoice, per the "quota applies once across the whole event"
     * decision — see that method's compositeAttributionForPhase() helper.
     */
    public function calculate(FestEvent $event, string $schoolId, array $schedule): array
    {
        // Routed through schoolRegistrationAmount() so this and the standalone helper
        // never drift apart — see that method for the tiered-vs-flat resolution.
        $school = Tenant::find($schoolId);
        $schoolReg = $school
            ? $this->schoolRegistrationAmount($school, $schedule, $event)
            : (float) ($schedule['school_registration_flat'] ?? $schedule['flat_amount'] ?? 2000);

        $perStudent = (float) ($schedule['per_student_amount'] ?? 300);
        $includedQuota = max(0, (int) ($schedule['included_items_per_student'] ?? 2));

        $eventIds = $event->reportableEventIds();

        // Per-student registration fee only applies to students who actually registered
        // for at least one item — an event-level-only (Step 1) registration with no
        // items must not be billed. (This previously counted every active event-level
        // registration outright, whether or not the student ever registered an item.)
        $studentIds = FestRegistration::whereIn('event_id', $eventIds)
            ->where('school_id', $schoolId)
            ->whereIn('status', ['submitted', 'approved', 'pending_approval'])
            ->with('participants')
            ->get()
            ->flatMap(fn (FestRegistration $r) => $r->participants
                ->where('participant_role', '!=', 'standby')
                ->pluck('student_id'))
            ->unique()
            ->filter()
            ->values();

        // Tracked only to decide whether the school registration fee still applies below
        // when the school has an event-level registration but no items yet.
        $hasEventRegistration = FestLevelRegistration::whereIn('event_id', $eventIds)
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->exists();

        $studentCount = $studentIds->count();
        $studentReg = round($studentCount * $perStudent, 2);

        $extraLines = [];
        $extraTotal = 0.0;

        // Ordered by id so item "position" (for the quota walk below) and each student's
        // "first item's phase" (for the once-per-event phase attribution below) agree on
        // one deterministic sequence — see FestSchoolEventFeeService::recalculateForPhase().
        $registrations = FestRegistration::whereIn('event_id', $eventIds)
            ->where('school_id', $schoolId)
            ->whereIn('status', ['submitted', 'approved', 'pending_approval'])
            ->whereHas('item', fn ($q) => $q->where('is_enabled', true))
            ->with(['item', 'participants'])
            ->orderBy('id')
            ->get();

        $itemCountByStudent = [];
        foreach ($registrations as $registration) {
            foreach ($registration->participants as $participant) {
                if ($participant->participant_role === 'standby' || ! $participant->student_id) {
                    continue;
                }
                $itemCountByStudent[$participant->student_id] = ($itemCountByStudent[$participant->student_id] ?? 0) + 1;
            }
        }

        // Once-per-event phase attribution (see FestSchoolEventFeeService::
        // recalculateForPhase()): a student's flat per-student fee is attributed to
        // whichever phase their EARLIEST item registration belongs to (position === 1
        // below); each item-level charge is attributed to that item's own phase.
        // Populated for free as the same position walk that drives the quota runs.
        $firstItemPhaseByStudent = [];
        $extraByPhase = [];
        $extraNoPhase = 0.0;

        $chargedRegistrations = [];
        foreach ($registrations as $registration) {
            foreach ($registration->participants as $participant) {
                if ($participant->participant_role === 'standby' || ! $participant->student_id) {
                    continue;
                }
                $studentId = $participant->student_id;
                $position = ($chargedRegistrations[$studentId] ?? 0) + 1;
                $chargedRegistrations[$studentId] = $position;
                $itemPhaseId = $registration->item?->phase_id;

                if ($position === 1) {
                    $firstItemPhaseByStudent[$studentId] = $itemPhaseId;
                }

                // Items within quota are covered by the student registration fee.
                if ($includedQuota > 0 && $position <= $includedQuota) {
                    continue;
                }

                // Quota 0 → every item billed separately at default item/head rates.
                // Quota N → items after N use extra item/head rates.
                $beyondQuota = $includedQuota > 0 && $position > $includedQuota;
                $amount = $this->itemFeeResolver->amountForItem(
                    $registration->item,
                    $schedule,
                    $event,
                    extraQuotaItem: $beyondQuota,
                );
                $suffix = $beyondQuota ? ' (extra)' : '';
                $lineType = $beyondQuota ? 'extra_item' : 'item_fee';
                $label = ($registration->item?->title ?? 'Item').$suffix;
                $extraLines[] = [
                    'line_type' => $lineType,
                    'label' => $label,
                    'quantity' => 1,
                    'unit_amount' => $amount,
                    'amount' => $amount,
                    'meta' => [
                        'student_id' => $studentId,
                        'item_id' => $registration->item_id,
                        'registration_id' => $registration->id,
                        'item_position' => $position,
                        'included_quota' => $includedQuota,
                        'phase_id' => $itemPhaseId,
                    ],
                ];
                $extraTotal += $amount;

                if ($itemPhaseId !== null) {
                    $extraByPhase[$itemPhaseId] = ($extraByPhase[$itemPhaseId] ?? 0.0) + $amount;
                } else {
                    $extraNoPhase += $amount;
                }
            }
        }

        // Fold each billed student's flat fee into the phase bucket of their first item.
        // A student counted in $studentIds but never seen in the walk above (their only
        // registration's item was later disabled, excluding it from $registrations but not
        // from $studentIds — a narrow, pre-existing inconsistency independent of phases) has
        // no attributable phase at all, tracked separately rather than dropped or guessed.
        $studentRegByPhase = [];
        $studentRegNoPhase = ['amount' => 0.0, 'student_count' => 0];
        $studentRegUnattributed = ['amount' => 0.0, 'student_count' => 0];

        foreach ($studentIds as $studentId) {
            if (! array_key_exists($studentId, $firstItemPhaseByStudent)) {
                $studentRegUnattributed['amount'] += $perStudent;
                $studentRegUnattributed['student_count']++;

                continue;
            }

            $phaseId = $firstItemPhaseByStudent[$studentId];
            if ($phaseId === null) {
                $studentRegNoPhase['amount'] += $perStudent;
                $studentRegNoPhase['student_count']++;

                continue;
            }

            $studentRegByPhase[$phaseId] ??= ['amount' => 0.0, 'student_count' => 0];
            $studentRegByPhase[$phaseId]['amount'] += $perStudent;
            $studentRegByPhase[$phaseId]['student_count']++;
        }

        foreach ($studentRegByPhase as &$bucket) {
            $bucket['amount'] = round($bucket['amount'], 2);
        }
        unset($bucket);
        $studentRegNoPhase['amount'] = round($studentRegNoPhase['amount'], 2);
        $studentRegUnattributed['amount'] = round($studentRegUnattributed['amount'], 2);

        // No school registration fee if the school hasn't registered anything at all
        // for this event — neither an event-level registration nor any item.
        if (! $hasEventRegistration && $studentCount === 0 && empty($extraLines)) {
            $schoolReg = 0.0;
        }

        $lines = [];
        if ($schoolReg > 0) {
            $lines[] = [
                'line_type' => 'school_reg',
                'label' => 'School registration fee',
                'quantity' => 1,
                'unit_amount' => $schoolReg,
                'amount' => $schoolReg,
            ];
        }
        if ($studentReg > 0) {
            $lines[] = [
                'line_type' => 'student_reg',
                'label' => "Student registration ({$studentCount} × ₹".number_format($perStudent, 0).')',
                'quantity' => $studentCount,
                'unit_amount' => $perStudent,
                'amount' => $studentReg,
            ];
        }
        foreach ($extraLines as $line) {
            $lines[] = $line;
        }

        return [
            'school_reg' => $schoolReg,
            'student_reg' => $studentReg,
            'student_count' => $studentCount,
            'extra_item' => round($extraTotal, 2),
            'included_quota' => $includedQuota,
            'lines' => $lines,
            'phase_attribution' => [
                'per_student_rate' => $perStudent,
                'student_reg' => [
                    'by_phase' => $studentRegByPhase,
                    'no_phase' => $studentRegNoPhase,
                    'unattributed' => $studentRegUnattributed,
                ],
                'extra_item' => [
                    'by_phase' => array_map(fn ($amount) => round($amount, 2), $extraByPhase),
                    'no_phase' => round($extraNoPhase, 2),
                ],
            ],
        ];
    }

    public function schoolRegistrationAmount(Tenant $school, array $schedule, FestEvent $event): float
    {
        // school_fee_mode='student_count_slab': the school self-selected one of the
        // admin-configured strength bands (FestSchoolFeeSlabSelectionService) rather than
        // being tiered by class category or a flat amount — see that service for why this
        // is a self-declared choice, not a count the system computes automatically. No
        // selection yet means no school registration fee line until they pick one.
        if (($schedule['school_fee_mode'] ?? 'class_tier') === 'student_count_slab') {
            $selection = FestSchoolFeeSlabSelection::where('event_id', $event->rootEvent()->id)
                ->where('school_id', $school->id)
                ->first();

            return $selection ? (float) $selection->amount : 0.0;
        }

        // Tiered-by-category takes over when a school_registration map is configured
        // (kalolsavam_composite; sports_composite events that never set one keep the flat
        // amount below unchanged) — same tier derivation and 'secondary' fallback as
        // FestSchoolEventFeeService::schoolRegistrationAmount() uses for cksc_tiered/item_catalog.
        $amounts = $schedule['school_registration'] ?? [];
        if (is_array($amounts) && $amounts !== []) {
            $tier = SchoolClassCategoryResolver::feeTierFor($school);

            return (float) ($amounts[$tier] ?? $amounts['secondary'] ?? 0);
        }

        return (float) ($schedule['school_registration_flat'] ?? $schedule['flat_amount'] ?? 2000);
    }
}
