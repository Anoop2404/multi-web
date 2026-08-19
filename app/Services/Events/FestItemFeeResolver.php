<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Support\FestSportsAgeGroup;
use Illuminate\Support\Collection;

class FestItemFeeResolver
{
    public function amountForItem(?FestEventItem $item, array $schedule, ?FestEvent $event = null, bool $extraQuotaItem = false, ?FestRegistration $registration = null): float
    {
        // Sports composite (and its non-sports Kalotsavam counterpart, kalolsavam_composite):
        // items inherit the event's rates (Head = Event) — except an explicit per-item fee
        // override, which always wins.
        if (in_array($schedule['fee_model'] ?? null, ['sports_composite', 'kalolsavam_composite'], true) || $event?->event_type === 'sports') {
            if ($item?->fee_amount !== null) {
                return (float) $item->fee_amount;
            }

            // Phase L: flat event fee + per-participant surcharge, opt-in via
            // group_item_flat_fee/group_item_per_participant_rate (item override, else
            // event-wide $schedule default). Returns null (falls through to the static
            // team_registration_fee/default_item_fee below) when neither is configured, so
            // items that never opted in keep exactly today's behavior.
            if ($item?->isTeamItem()) {
                $groupFee = $this->groupItemSurchargeAmount($item, $schedule, $registration);
                if ($groupFee !== null) {
                    return $groupFee;
                }
            }

            if ($item?->head_id) {
                $head = $item->relationLoaded('head')
                    ? $item->head
                    : $item->head()->first([
                        'id',
                        'student_registration_fee',
                        'team_registration_fee',
                        'default_item_fee',
                        'extra_item_fee',
                    ]);
                if ($head) {
                    if ($item->isTeamItem()) {
                        return (float) ($head->team_registration_fee ?? $head->default_item_fee ?? 0);
                    }
                    if ($extraQuotaItem && $head->extra_item_fee !== null) {
                        return (float) $head->extra_item_fee;
                    }

                    return (float) ($head->default_item_fee ?? $head->student_registration_fee ?? 0);
                }
            }

            if ($extraQuotaItem && isset($schedule['extra_item_fee']) && $schedule['extra_item_fee'] !== null && $schedule['extra_item_fee'] !== '') {
                return (float) $schedule['extra_item_fee'];
            }

            if (isset($schedule['default_item_fee']) && $schedule['default_item_fee'] !== '') {
                return (float) $schedule['default_item_fee'];
            }

            return 0.0;
        }

        if ($item?->fee_amount !== null) {
            return (float) $item->fee_amount;
        }

        // Phase L (non-sports/Kalotsavam group items): same opt-in flat + per-participant
        // surcharge as the sports branch above, checked right after the item's own
        // fee_amount override and before the area/head/participant-type-fee fallbacks below
        // — see groupItemSurchargeAmount() doc comment.
        $earlyParticipantType = $item?->participant_type ?? 'individual';
        if (in_array($earlyParticipantType, ['group', 'team'], true)) {
            $groupFee = $this->groupItemSurchargeAmount($item, $schedule, $registration);
            if ($groupFee !== null) {
                return $groupFee;
            }
        }

        // Competition area default fee (custom / non-sports types) — after item override, before head/schedule.
        if ($item?->area_id) {
            $area = $item->relationLoaded('area')
                ? $item->area
                : $item->area()->first(['id', 'default_item_fee', 'extra_item_fee']);
            if ($area) {
                if ($extraQuotaItem && $area->extra_item_fee !== null) {
                    return (float) $area->extra_item_fee;
                }
                if (! $extraQuotaItem && $area->default_item_fee !== null) {
                    return (float) $area->default_item_fee;
                }
            }
        }

        if ($item?->head_id) {
            $head = $item->relationLoaded('head') ? $item->head : $item->head()->first(['id', 'default_item_fee', 'extra_item_fee']);
            if ($head) {
                if ($extraQuotaItem && $head->extra_item_fee !== null) {
                    return (float) $head->extra_item_fee;
                }
                if (! $extraQuotaItem && $head->default_item_fee !== null) {
                    return (float) $head->default_item_fee;
                }
            }
        }

        $participantType = $item?->participant_type ?? 'individual';
        if (in_array($participantType, ['group', 'team'], true)) {
            $typeFees = $schedule['participant_type_fees'] ?? [];
            if (isset($typeFees[$participantType]) && $typeFees[$participantType] !== '') {
                return (float) $typeFees[$participantType];
            }
        }

        $eventType = $event?->event_type ?? 'kalolsavam';
        $ageGroup = FestSportsAgeGroup::resolveForItem($item?->age_group, $item?->class_group, $eventType);
        if ($ageGroup !== null) {
            $ageFees = $schedule['age_group_fees'] ?? [];
            if (isset($ageFees[$ageGroup]) && $ageFees[$ageGroup] !== '') {
                return (float) $ageFees[$ageGroup];
            }
        }

        $classGroup = $item?->class_group ?? 'open';
        $groupFees = $schedule['class_group_fees'] ?? [];
        if (isset($groupFees[$classGroup]) && $groupFees[$classGroup] !== '') {
            return (float) $groupFees[$classGroup];
        }

        if (isset($schedule['default_item_fee']) && $schedule['default_item_fee'] !== '') {
            return (float) $schedule['default_item_fee'];
        }

        return (float) ($schedule['per_item_amount'] ?? 0);
    }

    /**
     * Phase L — flat event fee + per-participant surcharge for group/team items:
     * `flat_fee + (per_participant_rate x actual FestGroup::participants()->count())`.
     *
     * Opt-in: an item only gets this treatment when group_item_flat_fee or
     * group_item_per_participant_rate is actually configured — item-level override
     * (FestEventItem::group_item_flat_fee/group_item_per_participant_rate) wins as a pair
     * over the event-wide $schedule default (fee_settings.group_item_flat_fee/
     * group_item_per_participant_rate), same "item override, else event default" shape as
     * every other per-item fee override in this resolver. Returns null — meaning "not
     * configured, caller should fall back to its existing static team/group fee" — when
     * neither level sets either field, so items that never opted in are completely
     * unaffected.
     *
     * See docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md Section 7.4 (Phase L).
     */
    public function groupItemSurchargeAmount(?FestEventItem $item, array $schedule, ?FestRegistration $registration): ?float
    {
        if ($item?->group_item_flat_fee !== null || $item?->group_item_per_participant_rate !== null) {
            $flatFee = (float) ($item->group_item_flat_fee ?? 0);
            $rate = (float) ($item->group_item_per_participant_rate ?? 0);
        } elseif (isset($schedule['group_item_flat_fee']) || isset($schedule['group_item_per_participant_rate'])) {
            $flatFee = (float) ($schedule['group_item_flat_fee'] ?? 0);
            $rate = (float) ($schedule['group_item_per_participant_rate'] ?? 0);
        } else {
            return null;
        }

        $count = $this->groupParticipantCount($registration, $schedule);

        return round($flatFee + ($rate * $count), 2);
    }

    /**
     * Actual participant count for a team/group registration's FestGroup, respecting the
     * same per-Sahodaya `charge_standbys` toggle already used everywhere else in the fee
     * engine (e.g. FestItemFeeResolver::participationBreakdown(), FestSchoolEventFeeService)
     * to decide whether standby rows count toward billing: when charge_standbys is falsy
     * (the default), participant_role='standby' rows are excluded from the count.
     */
    protected function groupParticipantCount(?FestRegistration $registration, array $schedule): int
    {
        if (! $registration) {
            return 0;
        }

        $group = $registration->relationLoaded('groups')
            ? $registration->groups->first()
            : $registration->groups()->first();

        if (! $group) {
            return 0;
        }

        $query = $group->participants();
        if (! ($schedule['charge_standbys'] ?? false)) {
            $query->where('participant_role', '!=', 'standby');
        }

        return $query->count();
    }

    /**
     * @param  ?int  $phaseId  When given, only registrations whose item is assigned to this
     *   FestEventPhase — used by FestSchoolEventFeeService::recalculateForPhase() so a
     *   Kalotsavam event with phase_mode_enabled can bill each phase independently. See
     *   docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md §3.
     * @return Collection<int, FestRegistration>
     */
    public function billableRegistrations(FestEvent $event, string $schoolId, ?int $phaseId = null): Collection
    {
        return FestRegistration::whereIn('event_id', $event->reportableEventIds())
            ->where('school_id', $schoolId)
            ->whereIn('status', ['submitted', 'approved', 'pending_approval'])
            ->when($phaseId !== null, fn ($q) => $q->whereHas('item', fn ($iq) => $iq->where('phase_id', $phaseId)))
            ->with(['item.head:id,name'])
            ->get();
    }

    /**
     * @param  ?int  $phaseId  See billableRegistrations().
     * @return array{total: float, count: int, lines: array<int, array{label: string, amount: float, item_id: ?int, item_title: string, head_name: ?string}>}
     */
    public function participationBreakdown(FestEvent $event, string $schoolId, array $schedule, ?int $phaseId = null): array
    {
        $lines = [];
        $total = 0.0;

        foreach ($this->billableRegistrations($event, $schoolId, $phaseId) as $registration) {
            $amount = $this->amountForItem($registration->item, $schedule, $event, registration: $registration);
            $itemTitle = $registration->item?->title ?? 'Registration #'.$registration->id;
            $headName = $registration->item?->head?->name;
            $label = $headName ? "{$headName} — {$itemTitle}" : $itemTitle;
            $lines[] = [
                'label' => $label,
                'item_title' => $itemTitle,
                'head_name' => $headName,
                'amount' => $amount,
                'item_id' => $registration->item_id,
            ];
            $total += $amount;
        }

        if ($schedule['charge_standbys'] ?? false) {
            $standbys = FestParticipant::query()
                ->whereHas('registration', fn ($q) => $q
                    ->whereIn('event_id', $event->reportableEventIds())
                    ->where('school_id', $schoolId)
                    ->whereIn('status', ['submitted', 'approved', 'pending_approval'])
                    ->when($phaseId !== null, fn ($q2) => $q2->whereHas('item', fn ($iq) => $iq->where('phase_id', $phaseId))))
                ->where('participant_role', 'standby')
                ->with(['student:id,name', 'registration.item'])
                ->get();

            foreach ($standbys as $participant) {
                $item = $participant->registration?->item;

                if ($item?->isTeamItem()) {
                    // A standby on a team/group entry doesn't field a whole extra team, so it
                    // shouldn't be charged the item's full team registration fee. Use the
                    // dedicated team-standby rate if the Sahodaya has set one; otherwise this
                    // standby isn't billed at all (avoids silently overcharging by the full
                    // team fee, which was the previous fallback behavior).
                    $amount = isset($schedule['team_standby_fee_amount']) && $schedule['team_standby_fee_amount'] !== ''
                        ? (float) $schedule['team_standby_fee_amount']
                        : 0.0;
                } else {
                    $amount = isset($schedule['default_item_fee']) && $schedule['default_item_fee'] !== ''
                        ? (float) $schedule['default_item_fee']
                        : $this->amountForItem($item, $schedule, $event, registration: $participant->registration);
                }

                $name = $participant->student?->name ?? 'Standby participant';
                $itemTitle = $participant->registration?->item?->title ?? 'Item';
                $lines[] = [
                    'label' => "Standby — {$name} ({$itemTitle})",
                    'amount' => $amount,
                    'item_id' => $participant->registration?->item_id,
                ];
                $total += $amount;
            }
        }

        return [
            'total' => round($total, 2),
            'count' => count($lines),
            'lines' => $lines,
        ];
    }

    /** @param  ?int  $phaseId  See billableRegistrations(). */
    public function participationTotal(FestEvent $event, string $schoolId, array $schedule, ?int $phaseId = null): float
    {
        return $this->participationBreakdown($event, $schoolId, $schedule, $phaseId)['total'];
    }
}
