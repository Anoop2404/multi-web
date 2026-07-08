<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FeeReceipt;
use App\Services\Fees\ProgramFeeReceiptService;
use App\Services\Fees\ReceiptEmailResendService;
use App\Support\TenancyDatabase;
use Illuminate\Http\Request;

class ReceiptEmailReportController extends SahodayaAdminController
{
    public function index(Request $request, ProgramFeeReceiptService $receiptService)
    {
        $schoolIds = TenancyDatabase::schoolIdsFor($this->sahodaya->id);

        $filters = $request->validate([
            'status' => 'nullable|in:all,sent,failed,skipped,queued,pending',
        ]);

        $status = $filters['status'] ?? 'all';

        $receipts = FeeReceipt::query()
            ->where('status', 'approved')
            ->orderByDesc('reviewed_at')
            ->limit(2000)
            ->get()
            ->filter(function (FeeReceipt $receipt) use ($schoolIds, $receiptService) {
                $schoolId = $receiptService->schoolIdForReceipt($receipt);

                return $schoolId && $schoolIds->contains($schoolId);
            })
            ->when($status !== 'all', function ($collection) use ($status) {
                if ($status === 'pending') {
                    return $collection->filter(fn (FeeReceipt $r) => blank($r->receipt_email_status));
                }

                return $collection->where('receipt_email_status', $status);
            })
            ->take(500)
            ->map(fn (FeeReceipt $r) => [
                'id'                   => $r->id,
                'receipt_number'       => $r->receipt_number,
                'amount'               => $r->amount,
                'payment_date'         => $r->payment_date?->toDateString(),
                'reviewed_at'          => $r->reviewed_at?->toDateTimeString(),
                'receipt_email_status' => $r->receipt_email_status ?? 'pending',
                'receipt_emailed_at'   => $r->receipt_emailed_at?->toDateTimeString(),
                'receipt_email_error'  => $r->receipt_email_error,
                'resend_count'         => $r->receipt_email_resend_count ?? 0,
            ])
            ->values();

        $counts = [
            'sent'    => $receipts->where('receipt_email_status', 'sent')->count(),
            'failed'  => $receipts->where('receipt_email_status', 'failed')->count(),
            'skipped' => $receipts->where('receipt_email_status', 'skipped')->count(),
            'pending' => $receipts->filter(fn ($r) => ($r['receipt_email_status'] ?? 'pending') === 'pending')->count(),
        ];

        return $this->inertia('Sahodaya/Finance/ReceiptEmailReport', [
            'receipts' => $receipts,
            'counts'   => $counts,
            'filters'  => ['status' => $status],
        ]);
    }

    public function resend(string $tenantId, FeeReceipt $feeReceipt, ProgramFeeReceiptService $receiptService, ReceiptEmailResendService $resend)
    {
        $schoolIds = TenancyDatabase::schoolIdsFor($this->sahodaya->id);
        $schoolId = $receiptService->schoolIdForReceipt($feeReceipt);
        abort_unless($schoolId && $schoolIds->contains($schoolId), 403);

        try {
            $resend->resend($feeReceipt);
        } catch (\Throwable $e) {
            return back()->with('error', 'Resend failed: '.$e->getMessage());
        }

        return back()->with('success', 'Receipt email resent.');
    }
}
