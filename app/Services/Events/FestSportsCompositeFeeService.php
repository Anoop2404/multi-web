<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestLevelRegistration;
use App\Models\FestRegistration;
use App\Models\Tenant;

class FestSportsCompositeFeeService
{
    public function __construct(
        private FestItemFeeResolver $itemFeeResolver,
    ) {}

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
