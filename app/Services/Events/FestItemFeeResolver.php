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
    public function amountForItem(?FestEventItem $item, array $schedule, ?FestEvent $event = null, bool $extraQuotaItem = false): float
    {
        // Sports composite: items inherit the sport event's rates (Head = Event) —
        // except an explicit per-item fee override, which always wins.
        if (($schedule['fee_model'] ?? null) === 'sports_composite' || $event?->event_type === 'sports') {
            if ($item?->fee_amount !== null) {
                return (float) $item->fee_amount;
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

            if (isset($schedule['default_item_fee']) && $schedule['default_item_fee'] !== '') {
                return (float) $schedule['default_item_fee'];
            }

            return 0.0;
        }

        if ($item?->fee_amount !== null) {
            return (float) $item->fee_amount;
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

    /** @return Collection<int, FestRegistration> */
    public function billableRegistrations(FestEvent $event, string $schoolId): Collection
    {
        return FestRegistration::where('event_id', $event->id)
            ->where('school_id', $schoolId)
            ->whereIn('status', ['submitted', 'approved'])
            ->with(['item.head:id,name'])
            ->get();
    }

    /**
     * @return array{total: float, count: int, lines: array<int, array{label: string, amount: float, item_id: ?int, item_title: string, head_name: ?string}>}
     */
    public function participationBreakdown(FestEvent $event, string $schoolId, array $schedule): array
    {
        $lines = [];
        $total = 0.0;

        foreach ($this->billableRegistrations($event, $schoolId) as $registration) {
            $amount = $this->amountForItem($registration->item, $schedule, $event);
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
                    ->where('event_id', $event->id)
                    ->where('school_id', $schoolId)
                    ->whereIn('status', ['submitted', 'approved']))
                ->where('participant_role', 'standby')
                ->with(['student:id,name', 'registration.item'])
                ->get();

            foreach ($standbys as $participant) {
                $amount = isset($schedule['default_item_fee']) && $schedule['default_item_fee'] !== ''
                    ? (float) $schedule['default_item_fee']
                    : $this->amountForItem($participant->registration?->item, $schedule, $event);
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

    public function participationTotal(FestEvent $event, string $schoolId, array $schedule): float
    {
        return $this->participationBreakdown($event, $schoolId, $schedule)['total'];
    }
}
