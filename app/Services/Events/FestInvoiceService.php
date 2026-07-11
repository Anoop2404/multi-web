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
        $feeService = app(FestSchoolEventFeeService::class);
        $schedule = $feeService->resolveSchedule($event);

        $existing = FestEventInvoice::where('event_id', $event->id)
            ->where('school_id', $school->id)
            ->first();

        if ($feeService->usesPerHeadBilling($event)) {
            return $this->issueForSchoolPerHead($event, $school, $feeService, $existing, $issuedBy);
        }

        $fee = FestSchoolEventFee::where('event_id', $event->id)
            ->where('school_id', $school->id)
            ->first();

        $itemCount = $feeService->billableItemCount($event, $school->id);
        $participationLines = $this->participationLinesForSchool($event, $school->id, $schedule);

        $schoolReg = $fee?->school_registration_fee ?? $feeService->schoolRegistrationAmount($school, $schedule);
        $partFee = $fee?->participation_fee ?? $feeService->participationFee($itemCount, $schedule);
        $total = (float) $schoolReg + (float) $partFee;

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

    /**
     * Per-head billed events: one invoice per school, but the totals and status are a rollup
     * across every Event Head's own FestSchoolEventFee record (each paid/approved independently).
     * The invoice is 'paid' only once ALL of the school's head records are fully paid.
     */
    private function issueForSchoolPerHead(
        FestEvent $event,
        Tenant $school,
        FestSchoolEventFeeService $feeService,
        ?FestEventInvoice $existing,
        ?int $issuedBy,
    ): FestEventInvoice {
        $headFees = $feeService->recalculateAllHeadsForSchool($event, $school->id);

        $schoolReg = round((float) $headFees->sum('school_registration_fee'), 2);
        $partFee = round((float) $headFees->sum('participation_fee'), 2);
        $itemCount = (int) $headFees->sum('participation_item_count');
        $total = round($schoolReg + $partFee, 2);

        $allPaid = $headFees->isNotEmpty() && $headFees->every(fn (FestSchoolEventFee $f) => $f->isFullyPaid());
        $status = $allPaid ? 'paid' : ($existing?->status === 'paid' ? 'issued' : ($existing?->status ?? 'issued'));

        $headLines = $headFees->map(fn (FestSchoolEventFee $f) => [
            'head_id'      => $f->head_id,
            'head_name'    => $f->head?->name,
            'total_due'    => (float) $f->total_due,
            'amount_paid'  => (float) $f->amount_paid,
            'status'       => $f->status,
        ])->values()->all();

        return FestEventInvoice::updateOrCreate(
            ['event_id' => $event->id, 'school_id' => $school->id],
            [
                'invoice_number'            => $existing?->invoice_number ?? FestEventInvoice::generateNumber($event),
                'school_registration_fee'   => $schoolReg,
                'participation_fee'         => $partFee,
                'participation_item_count'  => $itemCount,
                'total_amount'              => $total,
                'breakdown_json'            => [
                    'per_head' => true,
                    'heads' => $headLines,
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
        if ($invoice->breakdown_json['per_head'] ?? false) {
            return FestSchoolEventFee::where('event_id', $event->id)
                ->where('school_id', $invoice->school_id)
                ->whereNotNull('head_id')
                ->with('lines')
                ->get()
                ->flatMap(fn (FestSchoolEventFee $fee) => $fee->lines->map(fn ($line) => [
                    'label'     => $line->label,
                    'amount'    => (float) $line->amount,
                    'item_id'   => $line->meta['item_id'] ?? null,
                    'item_title'=> $line->label,
                    'head_name' => $line->meta['head_name'] ?? $fee->head?->name,
                ]))
                ->values()
                ->all();
        }

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
