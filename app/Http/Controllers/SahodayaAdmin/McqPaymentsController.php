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

        $base = McqSchoolFee::query()
            ->whereHas('exam', fn ($q) => $q->where('tenant_id', $this->sahodaya->id))
            ->with(['exam:id,title,exam_level,scheduled_at', 'school:id,name', 'feeReceipt']);

        $counts = [
            'pending'  => (clone $base)->whereIn('status', ['proof_uploaded'])->whereHas('feeReceipt', fn ($q) => $q->where('status', 'uploaded'))->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'all'      => (clone $base)->count(),
        ];

        $query = clone $base;
        if ($status === 'pending') {
            $query->whereIn('status', ['proof_uploaded'])
                ->whereHas('feeReceipt', fn ($q) => $q->where('status', 'uploaded'));
        } elseif ($status === 'approved') {
            $query->where('status', 'approved');
        }

        $fees = $query->orderByDesc('updated_at')->paginate(20)->withQueryString();

        $fees->getCollection()->transform(fn (McqSchoolFee $sf) => $this->mapFeeRow($sf));

        return $this->inertia('Sahodaya/Mcq/Payments/Index', [
            'fees'         => $fees,
            'activeStatus' => $status,
            'statusCounts' => $counts,
        ]);
    }

    public function exam(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $schoolFees = McqSchoolFee::where('exam_id', $exam->id)
            ->with(['school', 'feeReceipt'])
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

        $approvedCount = app(McqSchoolFeeService::class)->approve($schoolFee, $request->user()->id);

        if ($request->expectsJson()) {
            return response()->json(['message' => "{$approvedCount} registration(s) confirmed."]);
        }

        return back()->with('success', "School MCQ fee approved. {$approvedCount} registration(s) confirmed with hall tickets.");
    }

    public function proof(string $tenantId, McqSchoolFee $schoolFee)
    {
        abort_if($schoolFee->exam?->tenant_id !== $this->sahodaya->id, 403);
        abort_unless($schoolFee->feeReceipt?->file_path, 404);

        return TenantStorage::response($schoolFee->feeReceipt->file_path);
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
            'status'         => $sf->status,
            'updated_at'     => $sf->updated_at?->format('j M Y, g:i A'),
            'fee_receipt'    => $sf->feeReceipt ? [
                'id'              => $sf->feeReceipt->id,
                'status'          => $sf->feeReceipt->status,
                'amount'          => (float) $sf->feeReceipt->amount,
                'receipt_number'  => $sf->feeReceipt->receipt_number,
                'payment_date'    => $sf->feeReceipt->payment_date?->format('Y-m-d'),
                'transaction_ref' => $sf->feeReceipt->transaction_ref,
                'proof_url'       => $sf->feeReceipt->file_path
                    ? "/sahodaya-admin/{$this->sahodaya->id}/mcq/payments/{$sf->id}/proof"
                    : null,
            ] : null,
            'exam_url'       => "/sahodaya-admin/{$this->sahodaya->id}/mcq-exams/{$sf->exam_id}",
            'payments_url'   => "/sahodaya-admin/{$this->sahodaya->id}/mcq-exams/{$sf->exam_id}/payments",
        ];
    }
}
