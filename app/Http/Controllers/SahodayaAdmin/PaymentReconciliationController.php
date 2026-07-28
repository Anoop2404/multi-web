<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FeeReceipt;
use App\Models\FestEvent;
use App\Models\FestFeeCredit;
use App\Models\FestSchoolEventFee;
use App\Models\LedgerTransaction;
use App\Models\McqSchoolFee;
use App\Models\ProgramFeeCredit;
use App\Models\Tenant;
use App\Models\TrainingSchoolFee;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestFeeLedgerService;
use App\Services\Fees\CreditNoteService;
use App\Services\Fees\ProgramFeeReceiptService;
use App\Services\Ledger\FeeReceiptLedgerDispatcher;
use App\Services\Ledger\ProgramFeeCreditLedgerService;
use App\Support\TenancyDatabase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PaymentReconciliationController extends SahodayaAdminController
{
    public function index(Request $request, ProgramFeeReceiptService $receiptService)
    {
        $filters = $request->validate([
            'event_id' => 'nullable|integer',
            'school_id' => 'nullable|string',
            'type' => 'nullable|in:all,fest,mcq,training',
        ]);

        $schoolIds = TenancyDatabase::schoolIdsFor($this->sahodaya->id);
        $schools = Tenant::query()
            ->whereIn('id', $schoolIds)
            ->orderBy('name')
            ->get(['id', 'name']);
        $schoolNames = $schools->pluck('name', 'id');

        $rows = collect();
        if (($filters['type'] ?? 'all') === 'all' || ($filters['type'] ?? 'all') === 'fest') {
            $rows = $rows->concat($this->festRows($filters, $schoolNames));
        }
        if (empty($filters['event_id']) && (($filters['type'] ?? 'all') === 'all' || ($filters['type'] ?? 'all') === 'mcq')) {
            $rows = $rows->concat($this->programRows(McqSchoolFee::class, 'mcq', $filters, $schoolNames));
        }
        if (empty($filters['event_id']) && (($filters['type'] ?? 'all') === 'all' || ($filters['type'] ?? 'all') === 'training')) {
            $rows = $rows->concat($this->programRows(TrainingSchoolFee::class, 'training', $filters, $schoolNames));
        }

        $receiptIssues = $this->receiptIssues($rows, $receiptService, $schoolNames);

        return $this->inertia('Sahodaya/Finance/PaymentReconciliation', [
            'rows' => $rows->sortByDesc('unreconciled')->values(),
            'receiptIssues' => $receiptIssues->values(),
            'schools' => $schools,
            'events' => FestEvent::query()
                ->where('tenant_id', $this->sahodaya->id)
                ->orderByDesc('event_start')
                ->get(['id', 'title', 'event_type']),
            'filters' => array_merge([
                'event_id' => '',
                'school_id' => '',
                'type' => 'all',
            ], $filters),
            'summary' => [
                'exceptions' => $rows->where('unreconciled', '>', 0)->count(),
                'unreconciled' => round((float) $rows->sum('unreconciled'), 2),
                'recorded_credit' => round((float) $rows->sum('recorded_credit'), 2),
                'receipt_issues' => $receiptIssues->count(),
            ],
        ]);
    }

    public function recordCredit(Request $request, PlatformAuditLogger $audit)
    {
        $data = $request->validate([
            'carrier_type' => 'required|in:fest,mcq,training',
            'carrier_id' => 'required|integer',
            'reason' => 'nullable|string|max:500',
        ]);

        $modelClass = match ($data['carrier_type']) {
            'fest' => FestSchoolEventFee::class,
            'mcq' => McqSchoolFee::class,
            'training' => TrainingSchoolFee::class,
        };

        $credit = DB::transaction(function () use ($data, $modelClass) {
            /** @var FestSchoolEventFee|McqSchoolFee|TrainingSchoolFee $carrier */
            $carrier = $modelClass::query()->whereKey($data['carrier_id'])->lockForUpdate()->firstOrFail();
            $this->assertCarrierBelongsToSahodaya($carrier);

            $approved = round((float) $carrier->receipts()
                ->where('status', FeeReceipt::STATUS_APPROVED)
                ->sum('amount'), 2);
            $due = round((float) $carrier->total_due, 2);
            $existing = $this->outstandingCredit($carrier);
            $missing = round(max(0, $approved - $due - $existing), 2);

            abort_if($missing <= 0, 422, 'This fee no longer has an unreconciled overpayment.');

            $reason = $data['reason'] ?? 'Historical overpayment reconciled by Sahodaya finance admin';

            if ($carrier instanceof FestSchoolEventFee) {
                $credit = FestFeeCredit::create([
                    'fest_school_event_fee_id' => $carrier->id,
                    'amount' => $missing,
                    'reason' => $reason,
                    'created_by_user_id' => request()->user()?->id,
                ]);
                app(FestFeeLedgerService::class)->postCreditIssued($credit);
            } else {
                $credit = ProgramFeeCredit::create([
                    'creditable_type' => $carrier::class,
                    'creditable_id' => $carrier->id,
                    'source_type' => $carrier::class,
                    'source_id' => $carrier->id,
                    'amount' => $missing,
                    'reason' => $reason,
                    'created_by_user_id' => request()->user()?->id,
                ]);
                app(ProgramFeeCreditLedgerService::class)->postIssued($credit);
            }

            try {
                app(CreditNoteService::class)->issue($credit);
            } catch (\Throwable) {
                // Credit and ledger posting are authoritative; the note can be regenerated.
            }

            return $credit;
        });

        $audit->log(
            action: 'finance.overpayment_reconciled',
            description: 'Historical fee overpayment recorded as school credit',
            subject: $credit,
            properties: [
                'carrier_type' => $data['carrier_type'],
                'carrier_id' => $data['carrier_id'],
                'amount' => $credit->amount,
                'reason' => $credit->reason,
            ],
            category: 'finance',
        );

        return back()->with('success', '₹'.number_format((float) $credit->amount, 2).' recorded as school credit and posted to the ledger.');
    }

    public function refreshPaidState(Request $request, PlatformAuditLogger $audit)
    {
        $data = $request->validate([
            'carrier_type' => 'required|in:fest,mcq,training',
            'carrier_id' => 'required|integer',
        ]);
        $modelClass = match ($data['carrier_type']) {
            'fest' => FestSchoolEventFee::class,
            'mcq' => McqSchoolFee::class,
            'training' => TrainingSchoolFee::class,
        };

        /** @var FestSchoolEventFee|McqSchoolFee|TrainingSchoolFee $carrier */
        $carrier = $modelClass::findOrFail($data['carrier_id']);
        $this->assertCarrierBelongsToSahodaya($carrier);
        $before = (float) $carrier->amount_paid;
        $carrier->refreshPaidState();

        $audit->log(
            action: 'finance.amount_paid_refreshed',
            description: 'Recalculated stored paid total from approved receipts',
            subject: $carrier,
            properties: ['before' => $before, 'after' => (float) $carrier->fresh()->amount_paid],
            category: 'finance',
        );

        return back()->with('success', 'Paid total and status recalculated from approved receipts.');
    }

    public function repostReceipt(
        FeeReceipt $feeReceipt,
        ProgramFeeReceiptService $receiptService,
        FeeReceiptLedgerDispatcher $dispatcher,
        PlatformAuditLogger $audit,
    ) {
        abort_unless($feeReceipt->status === FeeReceipt::STATUS_APPROVED, 422, 'Only approved receipts can be reposted.');
        $schoolId = $receiptService->schoolIdForReceipt($feeReceipt);
        abort_unless($schoolId && Tenant::find($schoolId)?->parent_id === $this->sahodaya->id, 403);

        $dispatcher->postApproved($feeReceipt, $this->sahodaya->id, true);

        $audit->log(
            action: 'finance.receipt_ledger_reposted',
            description: "Reposted ledger journal for approved receipt #{$feeReceipt->id}",
            subject: $feeReceipt,
            properties: ['amount' => $feeReceipt->amount, 'school_id' => $schoolId],
            category: 'finance',
        );

        return back()->with('success', 'Receipt ledger journal rebuilt successfully.');
    }

    private function festRows(array $filters, Collection $schoolNames): Collection
    {
        return FestSchoolEventFee::query()
            ->forAmountAggregation()
            ->whereHas('event', fn ($query) => $query->where('tenant_id', $this->sahodaya->id))
            ->when($filters['event_id'] ?? null, fn ($query, $eventId) => $query->where('event_id', $eventId))
            ->when($filters['school_id'] ?? null, fn ($query, $schoolId) => $query->where('school_id', $schoolId))
            ->with(['event:id,title,event_type', 'receipts:id,feeable_type,feeable_id,status,amount,receipt_number,payment_date', 'credits'])
            ->get()
            ->map(fn (FestSchoolEventFee $fee) => $this->serializeCarrier(
                $fee,
                'fest',
                $fee->event?->title ?? "Event #{$fee->event_id}",
                $schoolNames,
            ))
            ->filter(fn (array $row) => $row['unreconciled'] > 0 || $row['paid_drift'] > 0);
    }

    private function programRows(string $modelClass, string $type, array $filters, Collection $schoolNames): Collection
    {
        $relation = $type === 'mcq' ? 'exam' : 'program';
        $tenantColumn = 'tenant_id';

        return $modelClass::query()
            ->whereHas($relation, fn ($query) => $query->where($tenantColumn, $this->sahodaya->id))
            ->when($filters['school_id'] ?? null, fn ($query, $schoolId) => $query->where('school_id', $schoolId))
            ->with([$relation, 'receipts'])
            ->get()
            ->map(function (Model $fee) use ($type, $relation, $schoolNames) {
                $program = $fee->{$relation};

                return $this->serializeCarrier(
                    $fee,
                    $type,
                    $program?->title ?? ucfirst($type).' #'.($fee->exam_id ?? $fee->program_id),
                    $schoolNames,
                );
            })
            ->filter(fn (array $row) => $row['unreconciled'] > 0 || $row['paid_drift'] > 0);
    }

    private function serializeCarrier(Model $fee, string $type, string $program, Collection $schoolNames): array
    {
        $approved = round((float) $fee->receipts->where('status', FeeReceipt::STATUS_APPROVED)->sum('amount'), 2);
        $due = round((float) $fee->total_due, 2);
        $storedPaid = round((float) $fee->amount_paid, 2);
        $overpayment = round(max(0, $approved - $due), 2);
        $credit = $this->outstandingCredit($fee);

        return [
            'carrier_type' => $type,
            'carrier_id' => $fee->id,
            'school_id' => $fee->school_id,
            'school_name' => $schoolNames->get($fee->school_id, $fee->school_id),
            'program' => $program,
            'total_due' => $due,
            'approved_receipts' => $approved,
            'stored_amount_paid' => $storedPaid,
            'paid_drift' => round(abs($approved - $storedPaid), 2),
            'overpayment' => $overpayment,
            'recorded_credit' => $credit,
            'unreconciled' => round(max(0, $overpayment - $credit), 2),
            'receipts' => $fee->receipts
                ->where('status', FeeReceipt::STATUS_APPROVED)
                ->map(fn (FeeReceipt $receipt) => [
                    'id' => $receipt->id,
                    'number' => $receipt->receipt_number,
                    'amount' => (float) $receipt->amount,
                    'payment_date' => $receipt->payment_date?->toDateString(),
                ])->values(),
        ];
    }

    private function receiptIssues(Collection $rows, ProgramFeeReceiptService $receiptService, Collection $schoolNames): Collection
    {
        $receiptIds = $rows->pluck('receipts')->flatten(1)->pluck('id')->unique();
        if ($receiptIds->isEmpty()) {
            return collect();
        }

        $posted = LedgerTransaction::query()
            ->where('tenant_id', $this->sahodaya->id)
            ->where('reference_type', FeeReceipt::class)
            ->whereIn('reference_id', $receiptIds)
            ->where('entry_type', 'credit')
            ->selectRaw('reference_id, SUM(amount) as total')
            ->groupBy('reference_id')
            ->pluck('total', 'reference_id');

        return FeeReceipt::whereIn('id', $receiptIds)->get()->map(function (FeeReceipt $receipt) use ($posted, $receiptService, $schoolNames) {
            $ledgerAmount = round((float) ($posted[$receipt->id] ?? 0), 2);
            $receiptAmount = round((float) $receipt->amount, 2);
            if (abs($ledgerAmount - $receiptAmount) <= 0.01) {
                return null;
            }
            $schoolId = $receiptService->schoolIdForReceipt($receipt);

            return [
                'receipt_id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'school_name' => $schoolNames->get($schoolId, $schoolId),
                'receipt_amount' => $receiptAmount,
                'ledger_amount' => $ledgerAmount,
                'issue' => $ledgerAmount <= 0 ? 'Missing ledger posting' : 'Ledger amount mismatch',
            ];
        })->filter();
    }

    private function outstandingCredit(Model $carrier): float
    {
        if ($carrier instanceof FestSchoolEventFee) {
            return round((float) FestFeeCredit::query()
                ->where('fest_school_event_fee_id', $carrier->id)
                ->whereNull('applied_at')
                ->sum('amount'), 2);
        }

        return round((float) ProgramFeeCredit::query()
            ->where('creditable_type', $carrier::class)
            ->where('creditable_id', $carrier->getKey())
            ->whereNull('applied_at')
            ->sum('amount'), 2);
    }

    private function assertCarrierBelongsToSahodaya(Model $carrier): void
    {
        $tenantId = match (true) {
            $carrier instanceof FestSchoolEventFee => $carrier->event?->tenant_id,
            $carrier instanceof McqSchoolFee => $carrier->exam?->tenant_id,
            $carrier instanceof TrainingSchoolFee => $carrier->program?->tenant_id,
            default => null,
        };

        abort_unless($tenantId === $this->sahodaya->id, 403);
    }
}
