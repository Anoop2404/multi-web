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

        $schoolPayments = FestSchoolEventFee::where('event_id', $event->id)
            ->forAmountAggregation()
            ->with(['school', 'feeReceipt', 'head'])
            ->orderBy('school_id')
            ->get()
            ->map(fn (FestSchoolEventFee $fee) => [
                'school'          => $fee->school?->name ?? $fee->school_id,
                'head'            => $fee->head?->name,
                'status'          => $fee->status,
                'total_due'       => (float) $fee->total_due,
                'receipt_number'  => $fee->feeReceipt?->receipt_number,
                'payment_date'    => $fee->feeReceipt?->payment_date?->toDateString(),
                'transaction_ref' => $fee->feeReceipt?->transaction_ref,
                'ledger_posted'   => $fee->status === 'approved' && $fee->feeReceipt?->status === 'approved',
            ]);

        $collected = (float) $schoolPayments->where('status', 'approved')->sum('total_due');

        return [
            'head'             => $head,
            'account_code'     => $code,
            'account_name'     => $head?->name ?? LedgerAccountCatalog::festIncomeHeadName($event),
            'transactions'     => $transactions,
            'school_payments'  => $schoolPayments,
            'summary'          => [
                'total_due'      => (float) $schoolPayments->sum('total_due'),
                'collected'      => $collected,
                'pending'        => $schoolPayments->where('status', 'pending')->count(),
                'awaiting'       => $schoolPayments->where('status', 'proof_uploaded')->count(),
                'ledger_credits' => (float) $transactions->where('entry_type', 'credit')->sum('amount'),
            ],
        ];
    }
}
