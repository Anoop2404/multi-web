<?php

namespace App\Services\Ledger;

use App\Models\AccountHead;
use App\Models\FestEvent;
use App\Models\FestSchoolEventFee;
use App\Models\LedgerTransaction;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Support\LedgerAccountCatalog;
use Illuminate\Support\Collection;

class LedgerReportingService
{
    /** @return Collection<int, object> */
    public function summaryByCategory(string $tenantId, ?string $from = null, ?string $to = null, ?int $financialYearId = null): Collection
    {
        $base = LedgerTransaction::query()
            ->where('ledger_transactions.tenant_id', $tenantId)
            ->when($financialYearId, fn ($q) => $q->where('ledger_transactions.financial_year_id', $financialYearId))
            ->when($from, fn ($q) => $q->where('transaction_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('transaction_date', '<=', $to))
            ->join('account_heads', 'account_heads.id', '=', 'ledger_transactions.account_head_id');

        return (clone $base)
            ->selectRaw("COALESCE(account_heads.category, 'other') as category, ledger_transactions.entry_type, SUM(ledger_transactions.amount) as total")
            ->groupByRaw("COALESCE(account_heads.category, 'other'), ledger_transactions.entry_type")
            ->orderBy('category')
            ->get();
    }

    /** @return Collection<int, object> */
    public function eventIncomeHeads(string $tenantId): Collection
    {
        return AccountHead::query()
            ->where('tenant_id', $tenantId)
            ->where('category', 'event')
            ->whereNotNull('event_id')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'event_id']);
    }

    /** @return Collection<int, object> */
    public function sportsIncomeHeads(string $tenantId): Collection
    {
        return AccountHead::query()
            ->where('tenant_id', $tenantId)
            ->where('category', 'sports')
            ->whereNotNull('event_id')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'event_id']);
    }

    /** @return Collection<int, object> */
    public function mcqExamIncomeHeads(string $tenantId): Collection
    {
        return AccountHead::query()
            ->where('tenant_id', $tenantId)
            ->where('category', 'mcq')
            ->whereNotNull('mcq_exam_id')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'mcq_exam_id']);
    }

    /** @return Collection<int, object> */
    public function trainingProgramIncomeHeads(string $tenantId): Collection
    {
        return AccountHead::query()
            ->where('tenant_id', $tenantId)
            ->where('category', 'training')
            ->whereNotNull('training_program_id')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'training_program_id']);
    }

    /** @return array<string, mixed> */
    public function trainingProgramPaymentLedger(TrainingProgram $program): array
    {
        $code = LedgerAccountCatalog::trainingProgramFeeCode($program->id);
        $head = AccountHead::where('tenant_id', $program->tenant_id)->where('code', $code)->first();

        $transactions = $head
            ? LedgerTransaction::where('tenant_id', $program->tenant_id)
                ->where('account_head_id', $head->id)
                ->orderByDesc('transaction_date')
                ->limit(200)
                ->get()
            : collect();

        $registrations = TrainingRegistration::where('program_id', $program->id)
            ->with(['teacher', 'school', 'feeReceipt'])
            ->orderBy('school_id')
            ->get()
            ->map(function (TrainingRegistration $registration) use ($program) {
                $receipt = $registration->feeReceipt;
                $amount = $receipt
                    ? (float) $receipt->amount
                    : (($program->usesPerTeacherFee()) ? (float) $program->fee_amount : 0.0);

                return [
                    'teacher'         => $registration->teacher?->name ?? "Registration #{$registration->id}",
                    'school'          => $registration->school?->name,
                    'status'          => $receipt?->status ?? $registration->status,
                    'amount'          => $amount,
                    'receipt_number'  => $receipt?->receipt_number,
                    'payment_date'    => $receipt?->payment_date?->toDateString(),
                    'ledger_posted'   => $receipt?->status === 'approved',
                ];
            });

        $schoolFees = \App\Models\TrainingSchoolFee::where('program_id', $program->id)
            ->with(['school', 'feeReceipt'])
            ->orderBy('school_id')
            ->get()
            ->map(fn (\App\Models\TrainingSchoolFee $fee) => [
                'teacher'         => 'Batch ('.$fee->teacher_count.' teachers)',
                'school'          => $fee->school?->name ?? $fee->school_id,
                'status'          => $fee->status,
                'amount'          => (float) $fee->total_due,
                'receipt_number'  => $fee->feeReceipt?->receipt_number,
                'payment_date'    => $fee->feeReceipt?->payment_date?->toDateString(),
                'ledger_posted'   => $fee->status === 'approved' && $fee->feeReceipt?->status === 'approved',
            ]);

        $rows = $program->usesSchoolBatchFee()
            ? $schoolFees
            : $registrations;

        $collected = (float) $rows
            ->filter(fn (array $row) => in_array($row['status'] ?? '', ['approved'], true))
            ->sum('amount');

        return [
            'head'            => $head,
            'account_code'    => $code,
            'account_name'    => $head?->name ?? LedgerAccountCatalog::trainingProgramIncomeHeadName($program),
            'transactions'    => $transactions,
            'registrations'   => $rows,
            'summary'         => [
                'total_due'      => (float) $rows->sum('amount'),
                'collected'      => $collected,
                'pending'        => $rows->whereIn('status', ['registered', 'pending'])->count(),
                'awaiting'       => $rows->whereIn('status', ['uploaded', 'proof_uploaded'])->count(),
                'ledger_credits' => (float) $transactions->where('entry_type', 'credit')->sum('amount'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function mcqExamPaymentLedger(\App\Models\McqExam $exam): array
    {
        $code = LedgerAccountCatalog::mcqExamFeeCode($exam->id);
        $head = AccountHead::where('tenant_id', $exam->tenant_id)->where('code', $code)->first();

        $transactions = $head
            ? LedgerTransaction::where('tenant_id', $exam->tenant_id)
                ->where('account_head_id', $head->id)
                ->orderByDesc('transaction_date')
                ->limit(200)
                ->get()
            : collect();

        $schoolFees = \App\Models\McqSchoolFee::where('exam_id', $exam->id)
            ->with(['school', 'feeReceipt'])
            ->orderBy('school_id')
            ->get()
            ->map(fn (\App\Models\McqSchoolFee $fee) => [
                'school'          => $fee->school?->name ?? $fee->school_id,
                'status'          => $fee->status,
                'total_due'       => (float) $fee->total_due,
                'student_count'   => (int) $fee->student_count,
                'receipt_number'  => $fee->feeReceipt?->receipt_number,
                'payment_date'    => $fee->feeReceipt?->payment_date?->toDateString(),
                'ledger_posted'   => $fee->status === 'approved' && $fee->feeReceipt?->status === 'approved',
            ]);

        $collected = (float) $schoolFees->where('status', 'approved')->sum('total_due');

        return [
            'head'             => $head,
            'account_code'     => $code,
            'account_name'     => $head?->name ?? LedgerAccountCatalog::mcqExamIncomeHeadName($exam),
            'transactions'     => $transactions,
            'school_payments'  => $schoolFees,
            'summary'          => [
                'total_due'      => (float) $schoolFees->sum('total_due'),
                'collected'      => $collected,
                'pending'        => $schoolFees->where('status', 'pending')->count(),
                'awaiting'       => $schoolFees->whereIn('status', ['proof_uploaded', 'submitted'])->count(),
                'ledger_credits' => (float) $transactions->where('entry_type', 'credit')->sum('amount'),
            ],
        ];
    }

    /** @return array{head: ?AccountHead, transactions: Collection, school_payments: Collection, summary: array<string, mixed>} */
    public function eventPaymentLedger(FestEvent $event): array
    {
        $code = LedgerAccountCatalog::festIncomeCode($event);
        $head = AccountHead::where('tenant_id', $event->tenant_id)
            ->where('code', $code)
            ->first();

        $transactions = $head
            ? LedgerTransaction::where('tenant_id', $event->tenant_id)
                ->where('account_head_id', $head->id)
                ->orderByDesc('transaction_date')
                ->limit(200)
                ->get()
            : collect();

        $schoolFees = FestSchoolEventFee::where('event_id', $event->id)
            ->forAmountAggregation()
            ->with(['school', 'feeReceipt', 'receipts', 'head', 'registrationBatch'])
            ->orderBy('school_id')
            ->get()
            ->map(function (FestSchoolEventFee $fee) {
                $primaryReceipt = $fee->feeReceipt ?? $fee->receipts->sortByDesc('id')->first();
                $hasPendingProof = $fee->receipts->contains(fn ($r) => !empty($r->file_path) && !in_array($r->status, ['approved', 'rejected', 'superseded', 'reversed'], true));
                $effectiveStatus = $fee->status;
                if ($effectiveStatus !== 'approved' && $hasPendingProof) {
                    $effectiveStatus = 'proof_uploaded';
                }

                return [
                    'id'                    => $fee->id,
                    'school_id'             => $fee->school_id,
                    'school'                => $fee->school?->name ?? $fee->school_id,
                    'head'                  => $fee->head?->name,
                    'registration_batch_id' => $fee->registration_batch_id,
                    'registration_batch'    => $fee->registrationBatch?->name,
                    'status'                => $effectiveStatus,
                    'total_due'             => (float) $fee->total_due,
                    'amount_paid'           => (float) $fee->amount_paid,
                    'balance_due'           => (float) $fee->outstandingBalance(),
                    'receipt_number'        => $primaryReceipt?->receipt_number,
                    'payment_date'          => $primaryReceipt?->payment_date?->format('d M Y'),
                    'transaction_ref'       => $primaryReceipt?->transaction_ref,
                    'ledger_posted'         => $effectiveStatus === 'approved' && ((float) $fee->amount_paid > 0 || $primaryReceipt?->status === 'approved'),
                    'receipts'              => $fee->receipts->sortByDesc('id')->map(fn ($r) => [
                        'id'              => $r->id,
                        'receipt_number'  => $r->receipt_number,
                        'amount'          => (float) $r->amount,
                        'status'          => $r->status,
                        'transaction_ref' => $r->transaction_ref,
                        'payment_date'    => $r->payment_date?->format('d M Y'),
                    ])->values()->all(),
                ];
            });

        $batchRows = $schoolFees->filter(fn (array $r) => $r['registration_batch_id'] !== null);
        $otherRows = $schoolFees->filter(fn (array $r) => $r['registration_batch_id'] === null);

        $combinedBatchRows = $batchRows->groupBy('school_id')->map(function ($group) use ($event) {
            $first = $group->first();
            $schoolId = $first['school_id'];

            $rollup = FestSchoolEventFee::where('event_id', $event->rootEvent()->id)
                ->where('school_id', $schoolId)
                ->whereNull('registration_batch_id')
                ->whereNull('phase_id')
                ->whereNull('head_id')
                ->first();

            $totalDue = $rollup ? (float) $rollup->total_due : round((float) $group->sum('total_due'), 2);
            $amountPaid = $rollup ? (float) $rollup->amount_paid : round((float) $group->sum('amount_paid'), 2);
            $balanceDue = round(max(0, $totalDue - $amountPaid), 2);

            $allReceipts = $group->flatMap(fn (array $r) => $r['receipts'])->sortByDesc('id')->unique('id')->values()->all();
            $primaryReceipt = collect($allReceipts)->first();

            $hasPendingProof = $group->contains(fn ($r) => ($r['status'] ?? '') === 'proof_uploaded');
            $status = $rollup?->status ?? $first['status'];
            if ($status !== 'approved' && $hasPendingProof) {
                $status = 'proof_uploaded';
            }

            return [
                'id'                    => $rollup?->id ?? $first['id'],
                'school_id'             => $schoolId,
                'school'                => $first['school'],
                'head'                  => null,
                'registration_batch_id' => null,
                'registration_batch'    => null,
                'status'                => $status,
                'total_due'             => $totalDue,
                'amount_paid'           => $amountPaid,
                'balance_due'           => $balanceDue,
                'receipt_number'        => $primaryReceipt['receipt_number'] ?? null,
                'payment_date'          => $primaryReceipt['payment_date'] ?? null,
                'transaction_ref'       => $primaryReceipt['transaction_ref'] ?? null,
                'ledger_posted'         => $status === 'approved' || $amountPaid >= $totalDue,
                'receipts'              => $allReceipts,
            ];
        })->values();

        $schoolPayments = $otherRows->concat($combinedBatchRows)
            ->filter(fn ($row) => (float) ($row['total_due'] ?? 0) > 0 || (float) ($row['amount_paid'] ?? 0) > 0)
            ->sortBy(fn ($row) => strtolower($row['school']))
            ->values();

        $settled = (float) $schoolPayments->sum(fn ($row) => min((float) $row['total_due'], (float) $row['amount_paid']));
        $totalPaid = (float) $schoolPayments->sum('amount_paid');
        $totalDue = (float) $schoolPayments->sum('total_due');
        $pendingBalance = (float) $schoolPayments->sum('balance_due');
        $overpayment = (float) $schoolPayments->sum(fn ($row) => max(0, (float) $row['amount_paid'] - (float) $row['total_due']));

        return [
            'head'             => $head,
            'account_code'     => $code,
            'account_name'     => $head?->name ?? LedgerAccountCatalog::festIncomeHeadName($event),
            'transactions'     => $transactions,
            'school_payments'  => $schoolPayments,
            'summary'          => [
                'total_schools'   => $schoolPayments->pluck('school_id')->unique()->count(),
                'total_due'       => round($totalDue, 2),
                'collected'       => round($settled, 2),
                'gross_receipts'  => round($totalPaid, 2),
                'pending_balance' => round($pendingBalance, 2),
                'overpayment'     => round($overpayment, 2),
                'approved'        => $schoolPayments->where('status', 'approved')->count(),
                'partial'         => $schoolPayments->where('status', 'partial')->count(),
                'pending'         => $schoolPayments->where('status', 'pending')->count(),
                'awaiting'        => $schoolPayments->where('status', 'proof_uploaded')->count(),
                'ledger_credits'  => (float) $transactions->where('entry_type', 'credit')->sum('amount'),
            ],
        ];
    }
}
