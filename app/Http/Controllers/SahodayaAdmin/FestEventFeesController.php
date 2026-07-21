<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestPageActivity;
use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Services\Events\FestSchoolEventFeeService;
use App\Services\Ledger\LedgerReportingService;
use Illuminate\Http\Request;
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
            ->forAmountAggregation()
            ->with(['school', 'feeReceipt', 'head'])
            ->orderBy('school_id')
            ->get()
            ->map(function (FestSchoolEventFee $fee) use ($feeService, $schedule, $event) {
                $regs = FestRegistration::where('event_id', $fee->event_id)
                    ->where('school_id', $fee->school_id)
                    ->whereIn('status', ['submitted', 'approved'])
                    ->with(['item', 'participants'])
                    ->get();

                $teamRegs = $regs->filter(fn ($r) => $r->item?->isTeamItem());
                $indivRegs = $regs->filter(fn ($r) => $r->item && ! $r->item->isTeamItem());

                $teamCount = $teamRegs->count();
                $indivCount = $indivRegs->count();
                $teamStudentsCount = 0;
                foreach ($teamRegs as $r) {
                    $teamStudentsCount += $r->participants
                        ->filter(fn ($p) => $p->participant_role !== 'standby' && $p->student_id)
                        ->count();
                }

                $sportsParticipation = $event->event_type === 'sports' ? [
                    'team_count' => $teamCount,
                    'team_students_count' => $teamStudentsCount,
                    'indiv_count' => $indivCount,
                ] : null;

                return [
                    'id' => $fee->id,
                    'school' => $fee->school?->name ?? $fee->school_id,
                    'school_id' => $fee->school_id,
                    'head' => $fee->head?->name,
                    'head_id' => $fee->head_id,
                    'status' => $fee->status,
                    'total_due' => $fee->total_due,
                    'amount_paid' => $fee->amount_paid,
                    'participation_item_count' => $fee->participation_item_count,
                    'school_registration_fee' => $fee->school_registration_fee,
                    'participation_fee' => $fee->participation_fee,
                    'breakdown' => $feeService->breakdown($event, $fee, $schedule),
                    'fee_receipt' => $fee->feeReceipt,
                    'items' => $regs->map(fn ($r) => $r->item?->title)->filter()->values(),
                    'sports_participation' => $sportsParticipation,
                ];
            })
            ->filter(fn ($row) => ($row['participation_item_count'] ?? 0) > 0 || count($row['items'] ?? []) > 0 || (float) ($row['total_due'] ?? 0) > 0)
            ->sortBy(fn ($row) => strtolower($row['school']))
            ->values();

        $summary = [
            'total_due'  => $schoolFees->sum('total_due'),
            'total_paid' => $schoolFees->sum('amount_paid'),
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

    public function pdfReport(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $feeService = app(FestSchoolEventFeeService::class);
        $schedule = $feeService->resolveSchedule($event);

        $schoolFees = FestSchoolEventFee::where('event_id', $event->id)
            ->forAmountAggregation()
            ->with(['school', 'feeReceipt', 'receipts', 'head'])
            ->orderBy('school_id')
            ->get()
            ->filter(fn ($fee) => (int) $fee->participation_item_count > 0 || (float) $fee->total_due > 0)
            ->map(function (FestSchoolEventFee $fee) use ($feeService, $schedule, $event) {
                $regs = FestRegistration::where('event_id', $fee->event_id)
                    ->where('school_id', $fee->school_id)
                    ->whereIn('status', ['submitted', 'approved'])
                    ->with(['item', 'participants'])
                    ->get();

                $receipts = $fee->receipts->map(fn ($r) => [
                    'receipt_number'  => $r->receipt_number,
                    'amount'          => (float) $r->amount,
                    'status'          => $r->status,
                    'transaction_ref' => $r->transaction_ref,
                    'payment_date'    => $r->payment_date?->format('d M Y'),
                ]);

                return [
                    'school_name'             => $fee->school?->name ?? $fee->school_id,
                    'head_name'               => $fee->head?->name,
                    'status'                  => $fee->status,
                    'school_registration_fee' => (float) $fee->school_registration_fee,
                    'participation_fee'       => (float) $fee->participation_fee,
                    'total_due'               => (float) $fee->total_due,
                    'amount_paid'             => (float) $fee->amount_paid,
                    'balance_due'             => (float) $fee->outstandingBalance(),
                    'item_count'              => (int) $fee->participation_item_count,
                    'receipt_no'              => $fee->feeReceipt?->receipt_number,
                    'payment_date'            => $fee->feeReceipt?->payment_date?->format('d M Y'),
                    'txn_ref'                 => $fee->feeReceipt?->transaction_ref,
                    'breakdown'               => $feeService->breakdown($event, $fee, $schedule),
                    'items'                   => $regs->map(fn ($r) => $r->item?->title)->filter()->unique()->values()->all(),
                    'receipts'                => $receipts,
                ];
            })
            ->sortBy(fn ($row) => strtolower($row['school_name']))
            ->values();

        $summary = [
            'total_schools' => $schoolFees->count(),
            'total_due'     => $schoolFees->sum('total_due'),
            'total_paid'    => $schoolFees->sum('amount_paid'),
            'total_balance' => $schoolFees->sum('balance_due'),
            'approved'      => $schoolFees->where('status', 'approved')->count(),
            'proof_uploaded'=> $schoolFees->where('status', 'proof_uploaded')->count(),
            'partial'       => $schoolFees->where('status', 'partial')->count(),
            'pending'       => $schoolFees->where('status', 'pending')->count(),
            'rejected'      => $schoolFees->where('status', 'rejected')->count(),
        ];

        $logoUrl = \App\Support\TenantBranding::logoUrl($this->sahodaya);
        $isDetailed = $request->boolean('detailed') || $request->query('view') === 'detailed';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.fest-fee-status-pdf', [
            'event'       => $event,
            'sahodaya'    => $this->sahodaya,
            'logoUrl'     => $logoUrl,
            'rows'        => $schoolFees,
            'summary'     => $summary,
            'isDetailed'  => $isDetailed,
            'generatedAt' => now()->format('d M Y, h:i A'),
        ])->setPaper('a4', 'landscape');

        $slug = \Illuminate\Support\Str::slug($event->title);
        $filename = "{$slug}-fee-status-report.pdf";

        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    public function exportPayments(string $tenantId, FestEvent $event): StreamedResponse
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $rows = FestSchoolEventFee::where('event_id', $event->id)
            ->forAmountAggregation()
            ->with(['school', 'feeReceipt', 'head'])
            ->orderBy('school_id')
            ->get()
            ->filter(fn ($fee) => (int) $fee->participation_item_count > 0 || (float) $fee->total_due > 0);

        $filename = 'event-fees-'.str($event->title)->slug('-').'.csv';

        return response()->streamDownload(function () use ($rows, $event) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Event', 'School', 'Head', 'Status', 'School reg fee', 'Participation fee', 'Total due', 'Receipt #', 'Payment date', 'Transaction ref']);
            foreach ($rows as $fee) {
                fputcsv($out, [
                    $event->title,
                    $fee->school?->name ?? $fee->school_id,
                    $fee->head?->name ?? '',
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
