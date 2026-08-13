<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\McqExam;
use App\Models\McqSchoolFee;
use App\Services\Mcq\McqSchoolFeeService;
use App\Support\TenantStorage;
use Illuminate\Http\Request;

class McqPaymentsController extends SahodayaAdminController
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');
        $search = trim((string) $request->query('search', ''));

        $base = McqSchoolFee::query()
            ->whereHas('exam', fn ($q) => $q->where('tenant_id', $this->sahodaya->id))
            ->with(['exam:id,title,exam_level,scheduled_at', 'school:id,name', 'feeReceipt', 'receipts' => fn ($q) => $q->latest('id')->with('reviewedBy:id,name')]);

        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                $q->whereHas('exam', fn ($eq) => $eq->where('title', 'like', "%{$search}%"))
                    ->orWhereHas('school', fn ($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        $counts = [
            'pending'  => (clone $base)->whereIn('status', ['proof_uploaded'])->whereHas('feeReceipt', fn ($q) => $q->where('status', 'uploaded'))->count(),
            'partial'  => (clone $base)->where('status', 'partial')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'rejected' => (clone $base)->whereHas('feeReceipt', fn ($q) => $q->where('status', 'rejected'))->count(),
            'all'      => (clone $base)->count(),
        ];

        $query = clone $base;
        if ($status === 'pending') {
            $query->whereIn('status', ['proof_uploaded'])
                ->whereHas('feeReceipt', fn ($q) => $q->where('status', 'uploaded'));
        } elseif ($status === 'partial') {
            $query->where('status', 'partial');
        } elseif ($status === 'approved') {
            $query->where('status', 'approved');
        } elseif ($status === 'rejected') {
            $query->whereHas('feeReceipt', fn ($q) => $q->where('status', 'rejected'));
        }

        $fees = $query->orderByDesc('updated_at')->paginate(20)->withQueryString();

        $fees->getCollection()->transform(fn (McqSchoolFee $sf) => $this->mapFeeRow($sf));

        return $this->inertia('Sahodaya/Mcq/Payments/Index', [
            'fees'         => $fees,
            'activeStatus' => $status,
            'statusCounts' => $counts,
            'search'       => $search,
        ]);
    }

    public function exam(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $schoolFees = McqSchoolFee::where('exam_id', $exam->id)
            ->with(['school', 'feeReceipt', 'receipts' => fn ($q) => $q->latest('id')->with('reviewedBy:id,name')])
            ->orderBy('school_id')
            ->get()
            ->map(fn (McqSchoolFee $sf) => $this->mapFeeRow($sf));

        $pendingCount = $schoolFees->filter(
            fn ($sf) => ($sf['fee_receipt']['status'] ?? null) === 'uploaded'
        )->count();

        return $this->inertia('Sahodaya/Mcq/Payments/Exam', [
            'exam'              => $exam->only('id', 'title', 'exam_level', 'status', 'fee_amount', 'fee_type'),
            'schoolFees'        => $schoolFees,
            'pendingCount'      => $pendingCount,
        ]);
    }

    public function approve(Request $request, string $tenantId, McqSchoolFee $schoolFee)
    {
        abort_if($schoolFee->exam?->tenant_id !== $this->sahodaya->id, 403);

        return $this->approveResponse($request, $schoolFee);
    }

    public function approveForExam(Request $request, string $tenantId, McqExam $exam, McqSchoolFee $schoolFee)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id || $schoolFee->exam_id !== $exam->id, 403);

        return $this->approveResponse($request, $schoolFee);
    }

    private function approveResponse(Request $request, McqSchoolFee $schoolFee)
    {
        $approvedCount = app(McqSchoolFeeService::class)->approve($schoolFee, $request->user()->id);

        if ($request->expectsJson()) {
            return response()->json(['message' => "{$approvedCount} registration(s) confirmed."]);
        }

        return back()->with('success', "School Talent Search fee approved. {$approvedCount} registration(s) confirmed with hall tickets.");
    }

    public function reject(Request $request, string $tenantId, McqSchoolFee $schoolFee)
    {
        abort_if($schoolFee->exam?->tenant_id !== $this->sahodaya->id, 403);

        return $this->rejectResponse($request, $schoolFee);
    }

    public function rejectForExam(Request $request, string $tenantId, McqExam $exam, McqSchoolFee $schoolFee)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id || $schoolFee->exam_id !== $exam->id, 403);

        return $this->rejectResponse($request, $schoolFee);
    }

    private function rejectResponse(Request $request, McqSchoolFee $schoolFee)
    {
        $data = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        app(McqSchoolFeeService::class)->reject($schoolFee, $request->user()->id, $data['rejection_reason']);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Payment proof rejected. School can re-upload.']);
        }

        return back()->with('success', 'Payment proof rejected. School can re-upload.');
    }

    public function proof(string $tenantId, McqSchoolFee $schoolFee)
    {
        abort_if($schoolFee->exam?->tenant_id !== $this->sahodaya->id, 403);
        abort_unless($schoolFee->feeReceipt?->file_path, 404);

        return TenantStorage::downloadResponse($this->sahodaya, $schoolFee->feeReceipt->file_path);
    }

    /** @return array<string, mixed> */
    private function mapFeeRow(McqSchoolFee $sf): array
    {
        return [
            'id'             => $sf->id,
            'exam_id'        => $sf->exam_id,
            'exam_title'     => $sf->exam?->title,
            'exam_level'     => $sf->exam?->exam_level,
            'school_id'      => $sf->school_id,
            'school_name'    => $sf->school?->name,
            'student_count'  => $sf->student_count,
            'total_due'      => (float) $sf->total_due,
            'amount_paid'    => (float) $sf->amount_paid,
            'status'         => $sf->status,
            'updated_at'     => $sf->updated_at?->format('j M Y, g:i A'),
            'fee_receipt'    => $sf->feeReceipt ? [
                'id'              => $sf->feeReceipt->id,
                'status'          => $sf->feeReceipt->status,
                'amount'          => (float) $sf->feeReceipt->amount,
                'receipt_number'  => $sf->feeReceipt->receipt_number,
                'payment_date'    => $sf->feeReceipt->payment_date?->format('Y-m-d'),
                'transaction_ref' => $sf->feeReceipt->transaction_ref,
                'rejection_reason'=> $sf->feeReceipt->rejection_reason,
                'proof_url'       => $sf->feeReceipt->file_path
                    ? "/sahodaya-admin/{$this->sahodaya->id}/mcq/payments/{$sf->id}/proof"
                    : null,
            ] : null,
            'receipts_history' => $this->mapReceiptsHistory($sf),
            'exam_url'       => "/sahodaya-admin/{$this->sahodaya->id}/mcq-exams/{$sf->exam_id}",
            'payments_url'   => "/sahodaya-admin/{$this->sahodaya->id}/mcq-exams/{$sf->exam_id}/payments",
        ];
    }

    /**
     * Every proof a school has uploaded for this batch fee, newest first — a batch fee can
     * collect several receipts over time as partial installments (see TracksPartialPayments),
     * and only the latest one is otherwise surfaced via `feeReceipt`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapReceiptsHistory(McqSchoolFee $sf): array
    {
        $receipts = $sf->relationLoaded('receipts') ? $sf->receipts : $sf->receipts()->latest('id')->with('reviewedBy:id,name')->get();

        return $receipts->map(fn ($r) => [
            'id'               => $r->id,
            'status'           => $r->status,
            'amount'           => (float) $r->amount,
            'receipt_number'   => $r->receipt_number,
            'transaction_ref'  => $r->transaction_ref,
            'payment_date'     => $r->payment_date?->format('Y-m-d'),
            'uploaded_at'      => $r->created_at?->format('j M Y, g:i A'),
            'reviewed_at'      => $r->reviewed_at?->format('j M Y, g:i A'),
            'reviewed_by'      => $r->reviewedBy?->name,
            'rejection_reason' => $r->rejection_reason,
            'reversal_reason'  => $r->reversal_reason,
            'proof_url'        => ($r->file_path && ! $r->isSystemCredit())
                ? "/sahodaya-admin/{$this->sahodaya->id}/finance/payments/receipts/{$r->id}/proof"
                : null,
            'receipt_url'      => in_array($r->status, ['approved', 'reversed'], true)
                ? "/sahodaya-admin/{$this->sahodaya->id}/finance/payments/receipts/{$r->id}"
                : null,
        ])->values()->all();
    }
}
