<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestPageActivity;
use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\Tenant;
use App\Services\Events\FestSchoolEventFeeService;
use App\Services\Ledger\LedgerReportingService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FestEventFeesController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $feeService = app(FestSchoolEventFeeService::class);
        $schedule = $feeService->resolveSchedule($event);

        FestRegistration::where('event_id', $event->id)
            ->distinct()
            ->pluck('school_id')
            ->each(fn (string $schoolId) => $feeService->recalculate($event, $schoolId));

        $schoolFees = FestSchoolEventFee::where('event_id', $event->id)
            ->with(['school', 'feeReceipt'])
            ->orderBy('school_id')
            ->get()
            ->map(function (FestSchoolEventFee $fee) use ($feeService, $schedule, $event) {
                $regs = FestRegistration::where('event_id', $fee->event_id)
                    ->where('school_id', $fee->school_id)
                    ->whereIn('status', ['submitted', 'approved'])
                    ->with('item')
                    ->get();

                return [
                    'id' => $fee->id,
                    'school' => $fee->school?->name ?? $fee->school_id,
                    'school_id' => $fee->school_id,
                    'status' => $fee->status,
                    'total_due' => $fee->total_due,
                    'participation_item_count' => $fee->participation_item_count,
                    'school_registration_fee' => $fee->school_registration_fee,
                    'participation_fee' => $fee->participation_fee,
                    'breakdown' => $feeService->breakdown($event, $fee, $schedule),
                    'fee_receipt' => $fee->feeReceipt,
                    'items' => $regs->map(fn ($r) => $r->item?->title)->filter()->values(),
                ];
            });

        $summary = [
            'total_due'  => $schoolFees->sum('total_due'),
            'total_paid' => $schoolFees->where('status', 'approved')->sum('total_due'),
            'pending'    => $schoolFees->where('status', 'pending')->count(),
            'awaiting'   => $schoolFees->where('status', 'proof_uploaded')->count(),
        ];

        return $this->inertia('Sahodaya/Events/Fees', $this->withEventActivity($event, FestPageActivity::FEES, [
            'event' => $event,
            'rows'  => $schoolFees->values(),
            'summary' => array_merge($summary, ['fee_model' => $schedule['fee_model'] ?? 'none']),
            'levelLabel' => config("fest_fees.level_labels.{$event->level_round}", $event->level_round),
            'feeSchedule' => $schedule,
            'feeConfigSource' => $feeService->feeConfigSource($event),
        ]));
    }

    public function ledger(string $tenantId, FestEvent $event, LedgerReportingService $reporting)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $ledger = $reporting->eventPaymentLedger($event);

        return $this->inertia('Sahodaya/Events/FeeLedger', $this->withEventActivity($event, FestPageActivity::FEES, [
            'event'          => $event,
            'accountCode'    => $ledger['account_code'],
            'accountName'    => $ledger['account_name'],
            'summary'        => $ledger['summary'],
            'schoolPayments' => $ledger['school_payments']->values(),
            'transactions'   => $ledger['transactions']->map(fn ($t) => [
                'id'               => $t->id,
                'transaction_date' => $t->transaction_date?->toDateString(),
                'entry_type'       => $t->entry_type,
                'amount'           => (float) $t->amount,
                'description'      => $t->description,
            ])->values(),
            'levelLabel' => config("fest_fees.level_labels.{$event->level_round}", $event->level_round),
        ]));
    }

    public function exportPayments(string $tenantId, FestEvent $event): StreamedResponse
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $rows = FestSchoolEventFee::where('event_id', $event->id)
            ->with(['school', 'feeReceipt'])
            ->orderBy('school_id')
            ->get();

        $filename = 'event-fees-'.str($event->title)->slug('-').'.csv';

        return response()->streamDownload(function () use ($rows, $event) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Event', 'School', 'Status', 'School reg fee', 'Participation fee', 'Total due', 'Receipt #', 'Payment date', 'Transaction ref']);
            foreach ($rows as $fee) {
                fputcsv($out, [
                    $event->title,
                    $fee->school?->name ?? $fee->school_id,
                    $fee->status,
                    $fee->school_registration_fee,
                    $fee->participation_fee,
                    $fee->total_due,
                    $fee->feeReceipt?->receipt_number,
                    $fee->feeReceipt?->payment_date?->toDateString(),
                    $fee->feeReceipt?->transaction_ref,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
