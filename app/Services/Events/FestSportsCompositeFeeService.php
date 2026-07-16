<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestItemHead;
use App\Models\FestLevelRegistration;
use App\Models\FestRegistration;
use App\Models\Tenant;

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

        $schoolReg = (float) ($fees['school_registration_fee'] ?? 0);
        $studentRegRate = (float) ($fees['student_registration_fee'] ?? 0);
        $teamRegRate = (float) ($fees['team_registration_fee'] ?? 0);
        $individualQuota = max(0, (int) ($fees['included_items_per_student'] ?? 0));
        $teamQuota = max(0, (int) ($fees['included_teams'] ?? 0));
        $defaultItemFee = $fees['default_item_fee'] ?? null;
        $extraItemFee = $fees['extra_item_fee'] ?? null;
        $label = $event->title ?: 'Sport event';

        $registrations = FestRegistration::where('event_id', $event->id)
            ->where('school_id', $schoolId)
            ->whereIn('status', ['submitted', 'approved'])
            // A withdrawn/disabled/deleted item should never keep billing a school —
            // whoever turned the item off is telling us it's no longer offered.
            ->whereHas('item', fn ($q) => $q->where('is_enabled', true))
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

            $eligible = (bool) ($registration->item->quota_eligible ?? false);
            $waived = $eligible && $teamQuotaUsed < $teamQuota;
            $itemOverride = $registration->item->fee_amount !== null ? (float) $registration->item->fee_amount : null;

            if ($waived) {
                $teamQuotaUsed++;
                $amount = 0.0;
                $label = ($registration->item->title ?? 'Team item').' — team fee (free quota)';
                $quantity = 1;
                $unitAmount = 0.0;
            } elseif ($itemOverride !== null) {
                $amount = $itemOverride;
                $label = ($registration->item->title ?? 'Team item').' — team fee (override)';
                $quantity = 1;
                $unitAmount = $itemOverride;
            } else {
                if ($teamRegRate == 0) {
                    $performersCount = $registration->participants
                        ->filter(fn ($p) => $p->participant_role !== 'standby' && $p->student_id)
                        ->count();
                    $itemFee = (float) ($defaultItemFee ?? $studentRegRate);
                    $amount = $itemFee * $performersCount;
                    $label = ($registration->item->title ?? 'Team item')." ({$performersCount} × ₹".number_format($itemFee, 0).")";
                    $quantity = $performersCount;
                    $unitAmount = $itemFee;
                } else {
                    $amount = $teamRegRate;
                    $label = ($registration->item->title ?? 'Team item').' — team fee';
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
                    'event_id' => $event->id,
                ],
            ];
            $teamFeeTotal += $amount;
        }

        $studentCount = count($studentsBilledBase);
        $studentRegTotal = round($studentCount * $studentRegRate, 2);

        $summaryLines = [];
        if ($schoolReg > 0) {
            $summaryLines[] = [
                'line_type' => 'school_reg',
                'label' => 'School registration fee ('.$label.')',
                'quantity' => 1,
                'unit_amount' => $schoolReg,
                'amount' => $schoolReg,
                'meta' => ['event_id' => $event->id],
            ];
        }
        if ($studentRegTotal > 0) {
            $summaryLines[] = [
                'line_type' => 'student_reg',
                'label' => "Student registration ({$label}) — {$studentCount} × ₹".number_format($studentRegRate, 0),
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

        return [
            'school_registration_fee' => null,
            'student_registration_fee' => null,
            'team_registration_fee' => null,
            'included_items_per_student' => 0,
            'included_teams' => 0,
            'default_item_fee' => null,
            'extra_item_fee' => null,
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

        $registrations = FestRegistration::where('event_id', $head->event_id)
            ->where('school_id', $schoolId)
            ->whereIn('status', ['submitted', 'approved'])
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

            $eligible = (bool) ($registration->item->quota_eligible ?? false);
            $waived = $eligible && $teamQuotaUsed < $teamQuota;
            $itemOverride = $registration->item->fee_amount !== null ? (float) $registration->item->fee_amount : null;

            if ($waived) {
                $teamQuotaUsed++;
                $amount = 0.0;
                $label = ($registration->item->title ?? 'Team item').' — team fee (free quota)';
                $quantity = 1;
                $unitAmount = 0.0;
            } elseif ($itemOverride !== null) {
                $amount = $itemOverride;
                $label = ($registration->item->title ?? 'Team item').' — team fee (override)';
                $quantity = 1;
                $unitAmount = $itemOverride;
            } else {
                if ($teamRegRate == 0) {
                    $performersCount = $registration->participants
                        ->filter(fn ($p) => $p->participant_role !== 'standby' && $p->student_id)
                        ->count();
                    $itemFee = (float) ($head->default_item_fee ?? $studentRegRate);
                    $amount = $itemFee * $performersCount;
                    $label = ($registration->item->title ?? 'Team item')." ({$performersCount} × ₹".number_format($itemFee, 0).")";
                    $quantity = $performersCount;
                    $unitAmount = $itemFee;
                } else {
                    $amount = $teamRegRate;
                    $label = ($registration->item->title ?? 'Team item').' — team fee';
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
     *   lines: list<array{line_type: string, label: string, quantity: int, unit_amount: float, amount: float, meta?: array}>
     * }
     */
    public function calculate(FestEvent $event, string $schoolId, array $schedule): array
    {
        $schoolReg = (float) ($schedule['school_registration_flat']
            ?? $schedule['flat_amount']
            ?? 2000);

        $perStudent = (float) ($schedule['per_student_amount'] ?? 300);
        $includedQuota = max(0, (int) ($schedule['included_items_per_student'] ?? 2));

        $studentIds = FestLevelRegistration::where('event_id', $event->id)
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->pluck('student_id')
            ->unique()
            ->filter()
            ->values();

        if ($studentIds->isEmpty()) {
            $studentIds = FestRegistration::where('event_id', $event->id)
                ->where('school_id', $schoolId)
                ->whereIn('status', ['submitted', 'approved'])
                ->with('participants')
                ->get()
                ->flatMap(fn (FestRegistration $r) => $r->participants
                    ->where('participant_role', '!=', 'standby')
                    ->pluck('student_id'))
                ->unique()
                ->filter()
                ->values();
        }

        $studentCount = $studentIds->count();
        $studentReg = round($studentCount * $perStudent, 2);

        $extraLines = [];
        $extraTotal = 0.0;

        $registrations = FestRegistration::where('event_id', $event->id)
            ->where('school_id', $schoolId)
            ->whereIn('status', ['submitted', 'approved'])
            ->whereHas('item', fn ($q) => $q->where('is_enabled', true))
            ->with(['item', 'participants'])
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

        $chargedRegistrations = [];
        foreach ($registrations as $registration) {
            foreach ($registration->participants as $participant) {
                if ($participant->participant_role === 'standby' || ! $participant->student_id) {
                    continue;
                }
                $studentId = $participant->student_id;
                $position = ($chargedRegistrations[$studentId] ?? 0) + 1;
                $chargedRegistrations[$studentId] = $position;

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
                    ],
                ];
                $extraTotal += $amount;
            }
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
        ];
    }

    public function schoolRegistrationAmount(Tenant $school, array $schedule): float
    {
        return (float) ($schedule['school_registration_flat'] ?? $schedule['flat_amount'] ?? 2000);
    }
}
