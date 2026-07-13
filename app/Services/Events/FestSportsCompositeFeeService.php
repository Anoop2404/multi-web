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
     * Per-Event-Head composite billing (the head owns its own School/Student/Team registration
     * fee, individual item quota, and team quota — see FestItemHead's fee columns).
     *
     * Billing rules, confirmed against real registration scenarios:
     *  - School Registration Fee: flat, charged once per school per head, regardless of item/
     *    student/team counts.
     *  - Student Registration Fee: flat, charged once per student per head who has at least one
     *    individual (non-team) item registered under this head — added ON TOP of, not replacing,
     *    that student's per-item charges.
     *  - Per individual item: charge the head's student_registration_fee as the per-item rate
     *    (items inherit head fees — FRD-04 v2; per-item fee_amount is ignored for sports_composite).
     *    If the item is quota_eligible and the student still has a free individual-quota slot under
     *    this head, the item is fully waived (0), and a slot is consumed.
     *    Quota is consumed in registration order (first quota-eligible item registered wins).
     *  - Team items (participant_type team/group) are billed ONCE per team registration via the
     *    head's team_registration_fee (item overrides ignored), not per team member,
     *    and are not subject to the individual Student Registration Fee. A separate team quota
     *    (included_teams) can waive the team fee the same way, consumed in registration order.
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
        $schoolReg = (float) ($head->school_registration_fee ?? 0);
        $studentRegRate = (float) ($head->student_registration_fee ?? 0);
        $teamRegRate = (float) ($head->team_registration_fee ?? 0);
        $individualQuota = max(0, (int) ($head->included_items_per_student ?? 0));
        $teamQuota = max(0, (int) ($head->included_teams ?? 0));

        $registrations = FestRegistration::where('event_id', $head->event_id)
            ->where('school_id', $schoolId)
            ->whereIn('status', ['submitted', 'approved'])
            ->whereHas('item', fn ($q) => $q->where('head_id', $head->id))
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

                if ($waived) {
                    $individualQuotaUsed[$studentId] = $used + 1;
                    $amount = 0.0;
                } else {
                    // FRD-04 v2: ignore item.fee_amount — inherit Event Head rates.
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

            if ($waived) {
                $teamQuotaUsed++;
                $amount = 0.0;
            } else {
                // FRD-04 v2: team items inherit the Event Head team registration fee.
                $amount = $teamRegRate;
            }

            $lines[] = [
                'line_type' => $waived ? 'team_fee_waived' : 'team_fee',
                'label' => ($registration->item->title ?? 'Team item').' — team fee'.($waived ? ' (free quota)' : ''),
                'quantity' => 1,
                'unit_amount' => $amount,
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
