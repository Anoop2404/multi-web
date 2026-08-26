<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\CsvSafety;
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
    public function index(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $selectedRegionId = $request->integer('region_id') ?: null;
        if ($selectedRegionId !== null) {
            $childEvent = $event->regionalChild($selectedRegionId) ?? FestEvent::find($selectedRegionId);
            if ($childEvent) {
                $event = $childEvent;
            }
        }

        $feeService = app(FestSchoolEventFeeService::class);
        $schedule = $feeService->resolveSchedule($event);
        $feeOwnerEvent = $feeService->feeOwnerEvent($event);
        $reportableEventIds = $event->reportableEventIds();

        $schoolIdsWithFees = FestSchoolEventFee::where('event_id', $feeOwnerEvent->id)->pluck('school_id');
        FestRegistration::whereIn('event_id', $reportableEventIds)
            ->distinct()
            ->pluck('school_id')
            ->diff($schoolIdsWithFees)
            ->each(fn (string $schoolId) => $feeService->recalculate($event, $schoolId));

        $regionSchoolIds = null;
        if ($event->parent_event_id !== null || $selectedRegionId !== null) {
            $regionSchoolIds = FestRegistration::whereIn('event_id', $reportableEventIds)
                ->distinct()
                ->pluck('school_id')
                ->all();
        }

        $schoolFees = FestSchoolEventFee::where('event_id', $feeOwnerEvent->id)
            ->when($regionSchoolIds !== null, fn ($q) => $q->whereIn('school_id', $regionSchoolIds))
            ->forAmountAggregation()
            ->with(['school', 'feeReceipt', 'receipts.attachments', 'head', 'registrationBatch', 'lines'])
            ->orderBy('school_id')
            ->get()
            ->map(function (FestSchoolEventFee $fee) use ($feeService, $schedule, $event, $reportableEventIds) {
                $regs = FestRegistration::whereIn('event_id', $reportableEventIds)
                    ->where('school_id', $fee->school_id)
                    ->whereIn('status', ['submitted', 'approved'])
                    ->when($fee->registration_batch_id, function ($query) use ($fee) {
                        $registrationIds = $fee->lines->pluck('meta')
                            ->map(fn ($meta) => $meta['registration_id'] ?? null)
                            ->filter();
                        $query->whereIn('id', $registrationIds);
                    })
                    ->with(['item', 'participants.student', 'participants.teacher'])
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

                $registeredStudents = $regs->flatMap(function ($r) {
                    return $r->participants->map(function ($p) use ($r) {
                        return [
                            'registration_id' => $r->id,
                            'item_title'      => $r->item?->title ?? 'Item',
                            'name'            => $p->student?->name ?? $p->teacher?->name ?? '—',
                            'reg_no'          => $p->student?->reg_no ?? '',
                            'role'            => $p->participant_role ?? 'performer',
                        ];
                    });
                })->values();

                $pendingReceipt = $fee->receipts->first(function ($r) {
                    return !empty($r->file_path) && !in_array($r->status, ['approved', 'rejected', 'superseded', 'reversed'], true);
                });
                $primaryReceipt = $pendingReceipt ?? $fee->feeReceipt ?? $fee->receipts->sortByDesc('id')->first();

                $hasPendingProof = $pendingReceipt !== null
                    || ($primaryReceipt && !empty($primaryReceipt->file_path) && !in_array($primaryReceipt->status, ['approved', 'rejected', 'superseded', 'reversed'], true));

                $effectiveStatus = $fee->status;
                if ($effectiveStatus !== 'approved' && $hasPendingProof) {
                    $effectiveStatus = 'proof_uploaded';
                }

                $allReceipts = $fee->receipts->sortByDesc('id')->values()->map(function ($r) use ($event, $fee) {
                    return [
                        'id'                => $r->id,
                        'status'            => $r->status,
                        'amount'            => (float) $r->amount,
                        'receipt_number'    => $r->receipt_number,
                        'transaction_ref'   => $r->transaction_ref,
                        'payment_date'      => $r->payment_date?->toDateString(),
                        'created_at'        => $r->created_at?->toIso8601String(),
                        'rejection_reason'  => $r->rejection_reason,
                        'is_system_credit'  => (bool) $r->is_system_credit,
                        'proof_url'         => ($r->file_path && ! $r->is_system_credit)
                            ? "/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}/school-fees/{$fee->id}/proofs/{$r->id}"
                            : null,
                        'attachments'       => $r->attachments->map(fn ($a) => [
                            'id'  => $a->id,
                            'url' => "/sahodaya-admin/{$this->sahodaya->id}/finance/payments/attachments/{$a->id}",
                        ])->values(),
                    ];
                });

                return [
                    'id' => $fee->id,
                    'school' => $fee->school?->name ?? $fee->school_id,
                    'school_id' => $fee->school_id,
                    'head' => $fee->head?->name,
                    'head_id' => $fee->head_id,
                    'registration_batch_id' => $fee->registration_batch_id,
                    'registration_batch' => $fee->registrationBatch?->name,
                    'status' => $effectiveStatus,
                    'total_due' => $fee->total_due,
                    'amount_paid' => $fee->amount_paid,
                    'participation_item_count' => $fee->participation_item_count,
                    'school_registration_fee' => $fee->school_registration_fee,
                    'participation_fee' => $fee->participation_fee,
                    'breakdown' => $feeService->breakdown($event, $fee, $schedule),
                    'fee_receipt' => $primaryReceipt,
                    'all_receipts' => $allReceipts,
                    'items' => $regs->map(fn ($r) => $r->item?->title)->filter()->values(),
                    'registered_students' => $registeredStudents,
                    'sports_participation' => $sportsParticipation,
                    'available_credit' => $fee->outstandingCredit(),
                    'item_allocation' => in_array($schedule['fee_model'] ?? null, ['item_catalog', 'per_item'], true)
                        ? $feeService->itemPaymentAllocation($event, $fee->school_id)
                        : [],
                ];
            })
            ->filter(fn ($row) => ($row['participation_item_count'] ?? 0) > 0 || count($row['items'] ?? []) > 0 || (float) ($row['total_due'] ?? 0) > 0)
            ->sortBy(fn ($row) => strtolower($row['school']))
            ->values();

        $summary = [
            'total_due'  => round((float) $schoolFees->sum('total_due'), 2),
            'total_paid' => round((float) $schoolFees->sum('amount_paid'), 2),
            'total_settled' => round((float) $schoolFees->sum(
                fn (array $row) => min((float) ($row['total_due'] ?? 0), (float) ($row['amount_paid'] ?? 0))
            ), 2),
            'overpayment' => round((float) $schoolFees->sum(
                fn (array $row) => max(0, (float) ($row['amount_paid'] ?? 0) - (float) ($row['total_due'] ?? 0))
            ), 2),
            'recorded_credit' => round((float) $schoolFees->sum('available_credit'), 2),
            'pending'    => $schoolFees->where('status', 'pending')->count(),
            'awaiting'   => $schoolFees->where('status', 'proof_uploaded')->count(),
            'approved'   => $schoolFees->where('status', 'approved')->count(),
            'total_schools' => $schoolFees->pluck('school_id')->unique()->count(),
        ];
        $summary['unreconciled_overpayment'] = round(
            max(0, $summary['overpayment'] - $summary['recorded_credit']),
            2,
        );

        $childEvents = $event->sportEventDropdownOptions();

        return $this->inertia('Sahodaya/Events/Fees', $this->withEventActivity($event, FestPageActivity::FEES, [
            'event' => $event,
            'rows'  => $schoolFees->values(),
            'summary' => array_merge($summary, ['fee_model' => $schedule['fee_model'] ?? 'none']),
            'levelLabel' => config("fest_fees.level_labels.{$event->level_round}", $event->level_round),
            'feeSchedule' => $schedule,
            'feeConfigSource' => $feeService->feeConfigSource($event),
            'childEvents' => $childEvents,
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

    public function pdfReport(Request $request, string $tenantId, string $event)
    {
        // Implicit route-model binding was found to unreliably deliver the resolved model
        // to PDF/file-download controller methods in production (see downloadPdf() in
        // BoardResultVerificationController for the first confirmed case). Resolving
        // manually here avoids the same class of failure.
        $event = FestEvent::findOrFail($event);
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $selectedRegionId = $request->integer('region_id') ?: null;
        if ($selectedRegionId !== null) {
            $childEvent = $event->regionalChild($selectedRegionId) ?? FestEvent::find($selectedRegionId);
            if ($childEvent) {
                $event = $childEvent;
            }
        }

        $feeService = app(FestSchoolEventFeeService::class);
        $schedule = $feeService->resolveSchedule($event);
        $feeOwnerEvent = $feeService->feeOwnerEvent($event);
        $reportableEventIds = $event->reportableEventIds();

        $schoolIdsWithFees = FestSchoolEventFee::where('event_id', $feeOwnerEvent->id)->pluck('school_id');
        FestRegistration::whereIn('event_id', $reportableEventIds)
            ->distinct()
            ->pluck('school_id')
            ->diff($schoolIdsWithFees)
            ->each(fn (string $schoolId) => $feeService->recalculate($event, $schoolId));

        $regionSchoolIds = null;
        if ($event->parent_event_id !== null || $selectedRegionId !== null) {
            $regionSchoolIds = FestRegistration::whereIn('event_id', $reportableEventIds)
                ->distinct()
                ->pluck('school_id')
                ->all();
        }

        $schoolFees = FestSchoolEventFee::where('event_id', $feeOwnerEvent->id)
            ->when($regionSchoolIds !== null, fn ($q) => $q->whereIn('school_id', $regionSchoolIds))
            ->forAmountAggregation()
            ->with(['school', 'feeReceipt', 'receipts', 'head', 'registrationBatch', 'lines'])
            ->orderBy('school_id')
            ->get()
            ->map(function (FestSchoolEventFee $fee) use ($feeService, $schedule, $event, $reportableEventIds) {
                $regs = FestRegistration::whereIn('event_id', $reportableEventIds)
                    ->where('school_id', $fee->school_id)
                    ->whereIn('status', ['submitted', 'approved'])
                    ->when($fee->registration_batch_id, function ($query) use ($fee) {
                        $registrationIds = $fee->lines->pluck('meta')
                            ->map(fn ($meta) => $meta['registration_id'] ?? null)
                            ->filter();
                        $query->whereIn('id', $registrationIds);
                    })
                    ->with(['item', 'participants'])
                    ->get();

                $receipts = $fee->receipts->map(fn ($r) => [
                    'receipt_number'  => $r->receipt_number,
                    'amount'          => (float) $r->amount,
                    'status'          => $r->status,
                    'transaction_ref' => $r->transaction_ref,
                    'payment_date'    => $r->payment_date?->format('d M Y'),
                ]);

                $pendingReceipt = $fee->receipts->first(function ($r) {
                    return !empty($r->file_path) && !in_array($r->status, ['approved', 'rejected', 'superseded', 'reversed'], true);
                });
                $primaryReceipt = $pendingReceipt ?? $fee->feeReceipt ?? $fee->receipts->sortByDesc('id')->first();

                $hasPendingProof = $pendingReceipt !== null
                    || ($primaryReceipt && !empty($primaryReceipt->file_path) && !in_array($primaryReceipt->status, ['approved', 'rejected', 'superseded', 'reversed'], true));

                $effectiveStatus = $fee->status;
                if ($effectiveStatus !== 'approved' && $hasPendingProof) {
                    $effectiveStatus = 'proof_uploaded';
                }

                $items = $regs->map(fn ($r) => $r->item?->title)->filter()->unique()->values()->all();

                return [
                    'school_id'               => $fee->school_id,
                    'school_name'             => $fee->school?->name ?? $fee->school_id,
                    'head_name'               => $fee->head?->name,
                    'registration_batch'      => $fee->registrationBatch?->name,
                    'status'                  => $effectiveStatus,
                    'school_registration_fee' => (float) $fee->school_registration_fee,
                    'participation_fee'       => (float) $fee->participation_fee,
                    'total_due'               => (float) $fee->total_due,
                    'amount_paid'             => (float) $fee->amount_paid,
                    'balance_due'             => (float) $fee->outstandingBalance(),
                    // See docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §14 — money owed BACK
                    // to this school (rejected/cancelled paid items), shown alongside
                    // balance_due rather than netted into it.
                    'available_credit'        => $fee->outstandingCredit(),
                    'item_count'              => (int) $fee->participation_item_count,
                    'receipt_no'              => $primaryReceipt?->receipt_number,
                    'payment_date'            => $primaryReceipt?->payment_date?->format('d M Y'),
                    'txn_ref'                 => $primaryReceipt?->transaction_ref,
                    'breakdown'               => $feeService->breakdown($event, $fee, $schedule),
                    'items'                   => $items,
                    'receipts'                => $receipts,
                ];
            })
            ->filter(fn ($row) => ($row['item_count'] ?? 0) > 0 || count($row['items'] ?? []) > 0 || (float) ($row['total_due'] ?? 0) > 0);

        $statusRank = [
            'approved'       => 1,
            'proof_uploaded' => 2,
            'partial'        => 3,
            'pending'        => 4,
            'rejected'       => 5,
        ];

        $schoolFees = $schoolFees->sortBy(function ($row) use ($statusRank) {
            $rank = $statusRank[$row['status']] ?? 9;
            return sprintf('%d_%s', $rank, strtolower($row['school_name']));
        })->values();

        $summary = [
            'total_schools' => $schoolFees->pluck('school_id')->unique()->count(),
            'total_due'     => $schoolFees->sum('total_due'),
            'total_paid'    => $schoolFees->sum('amount_paid'),
            'total_balance' => $schoolFees->sum('balance_due'),
            'total_credit'  => $schoolFees->sum('available_credit'),
            'approved'      => $schoolFees->where('status', 'approved')->count(),
            'proof_uploaded'=> $schoolFees->where('status', 'proof_uploaded')->count(),
            'partial'       => $schoolFees->where('status', 'partial')->count(),
            'pending'       => $schoolFees->where('status', 'pending')->count(),
            'rejected'      => $schoolFees->where('status', 'rejected')->count(),
        ];

        $logoUrl = \App\Support\TenantBranding::logoEmbedSrc($this->sahodaya) ?? \App\Support\TenantBranding::logoUrl($this->sahodaya);
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

    public function exportPayments(Request $request, string $tenantId, FestEvent $event): StreamedResponse
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $selectedRegionId = $request->integer('region_id') ?: null;
        if ($selectedRegionId !== null) {
            $childEvent = $event->regionalChild($selectedRegionId) ?? FestEvent::find($selectedRegionId);
            if ($childEvent) {
                $event = $childEvent;
            }
        }

        $feeService = app(FestSchoolEventFeeService::class);
        $feeOwnerEvent = $feeService->feeOwnerEvent($event);
        $reportableEventIds = $event->reportableEventIds();

        $schoolIdsWithFees = FestSchoolEventFee::where('event_id', $feeOwnerEvent->id)->pluck('school_id');
        FestRegistration::whereIn('event_id', $reportableEventIds)
            ->distinct()
            ->pluck('school_id')
            ->diff($schoolIdsWithFees)
            ->each(fn (string $schoolId) => $feeService->recalculate($event, $schoolId));

        $regionSchoolIds = null;
        if ($event->parent_event_id !== null || $selectedRegionId !== null) {
            $regionSchoolIds = FestRegistration::whereIn('event_id', $reportableEventIds)
                ->distinct()
                ->pluck('school_id')
                ->all();
        }

        $rows = FestSchoolEventFee::where('event_id', $feeOwnerEvent->id)
            ->when($regionSchoolIds !== null, fn ($q) => $q->whereIn('school_id', $regionSchoolIds))
            ->forAmountAggregation()
            ->with(['school', 'feeReceipt', 'head', 'registrationBatch'])
            ->orderBy('school_id')
            ->get()
            ->filter(fn ($fee) => (int) $fee->participation_item_count > 0 || (float) $fee->total_due > 0);

        $filename = 'event-fees-'.str($event->title)->slug('-').'.csv';

        return response()->streamDownload(function () use ($rows, $event) {
            $out = fopen('php://output', 'w');
            CsvSafety::fputcsv($out, ['Event', 'School', 'Head / payment level', 'Status', 'School reg fee', 'Participation fee', 'Total due', 'Receipt #', 'Payment date', 'Transaction ref', 'Credit owed']);
            foreach ($rows as $fee) {
                CsvSafety::fputcsv($out, [
                    $event->title,
                    $fee->school?->name ?? $fee->school_id,
                    $fee->registrationBatch?->name ?? $fee->head?->name ?? '',
                    $fee->status,
                    $fee->school_registration_fee,
                    $fee->participation_fee,
                    $fee->total_due,
                    $fee->feeReceipt?->receipt_number,
                    $fee->feeReceipt?->payment_date?->toDateString(),
                    $fee->feeReceipt?->transaction_ref,
                    // See docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §14.
                    $fee->outstandingCredit(),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
