<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventInvoice;
use App\Models\FestSchoolEventFee;
use App\Models\Tenant;
use App\Services\Events\FestItemFeeResolver;

class FestInvoiceService
{
    public function issueForSchool(FestEvent $event, Tenant $school, ?int $issuedBy = null): FestEventInvoice
    {
        $fee = FestSchoolEventFee::where('event_id', $event->id)
            ->where('school_id', $school->id)
            ->first();

        $schedule = app(FestSchoolEventFeeService::class)->resolveSchedule($event);
        $itemCount = app(FestSchoolEventFeeService::class)->billableItemCount($event, $school->id);
        $participationLines = $this->participationLinesForSchool($event, $school->id, $schedule);

        $schoolReg = $fee?->school_registration_fee ?? app(FestSchoolEventFeeService::class)->schoolRegistrationAmount($school, $schedule);
        $partFee = $fee?->participation_fee ?? app(FestSchoolEventFeeService::class)->participationFee($itemCount, $schedule);
        $total = (float) $schoolReg + (float) $partFee;

        $existing = FestEventInvoice::where('event_id', $event->id)
            ->where('school_id', $school->id)
            ->first();

        $status = ($fee?->status === 'approved' || $existing?->status === 'paid')
            ? 'paid'
            : ($existing?->status ?? 'issued');

        return FestEventInvoice::updateOrCreate(
            ['event_id' => $event->id, 'school_id' => $school->id],
            [
                'invoice_number'            => $existing?->invoice_number ?? FestEventInvoice::generateNumber($event),
                'school_registration_fee'   => $schoolReg,
                'participation_fee'         => $partFee,
                'participation_item_count'  => $itemCount,
                'total_amount'              => $total,
                'breakdown_json'            => [
                    'schedule' => $schedule,
                    'school_registration' => $schoolReg,
                    'participation' => ['items' => $itemCount, 'amount' => $partFee],
                    'participation_lines' => $participationLines,
                ],
                'status'    => $status,
                'issued_at' => $existing?->issued_at ?? now(),
                'issued_by' => $issuedBy ?? $existing?->issued_by,
            ]
        );
    }

    /** @return list<array{label: string, amount: float, item_id: ?int, item_title: string, head_name: ?string}> */
    public function participationLines(FestEvent $event, FestEventInvoice $invoice): array
    {
        $stored = $invoice->breakdown_json['participation_lines'] ?? null;
        if (is_array($stored) && $stored !== []) {
            return $stored;
        }

        $schedule = app(FestSchoolEventFeeService::class)->resolveSchedule($event);

        return $this->participationLinesForSchool($event, $invoice->school_id, $schedule);
    }

    /** @return array{event: FestEvent, invoice: FestEventInvoice, sahodaya: Tenant, participationLines: list<array<string, mixed>>} */
    public function invoiceViewData(FestEvent $event, FestEventInvoice $invoice, Tenant $sahodaya): array
    {
        return [
            'event' => $event,
            'invoice' => $invoice,
            'sahodaya' => $sahodaya,
            'participationLines' => $this->participationLines($event, $invoice),
        ];
    }

    /** @return list<array{label: string, amount: float, item_id: ?int, item_title: string, head_name: ?string}> */
    private function participationLinesForSchool(FestEvent $event, string $schoolId, array $schedule): array
    {
        return app(FestItemFeeResolver::class)
            ->participationBreakdown($event, $schoolId, $schedule)['lines'];
    }

    /** @return list<FestEventInvoice> */
    public function issueAll(FestEvent $event, ?int $issuedBy = null): array
    {
        $schools = Tenant::where('parent_id', $event->tenant_id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->get();

        $issued = [];
        foreach ($schools as $school) {
            if (app(FestSchoolEventFeeService::class)->billableItemCount($event, $school->id) > 0
                || app(FestSchoolEventFeeService::class)->feeRequired($event)) {
                $issued[] = $this->issueForSchool($event, $school, $issuedBy);
            }
        }

        return $issued;
    }
}
