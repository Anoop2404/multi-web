<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FeeReceipt;
use App\Models\MembershipPayment;
use App\Services\Exports\CsvExportDispatcher;
use App\Services\Fees\ProgramFeeReceiptService;
use App\Services\Fees\SchoolPaymentHistoryService;
use App\Services\Membership\MembershipReceiptService;
use Illuminate\Http\Request;

class PaymentHistoryController extends SchoolAdminController
{
    public function index(SchoolPaymentHistoryService $history)
    {
        $payments = $history->rowsForSchool($this->school);

        $summary = [
            'total'      => (float) $payments->sum('amount'),
            'membership' => (float) $payments->where('type', 'membership')->sum('amount'),
            'fest'       => (float) $payments->where('type', 'fest')->sum('amount'),
            'training'   => (float) $payments->where('type', 'training')->sum('amount'),
            'mcq'        => (float) $payments->where('type', 'mcq')->sum('amount'),
            'approved'   => $payments->whereIn('status', ['verified', 'approved'])->count(),
            'pending'    => $payments->whereIn('status', ['pending', 'uploaded', 'submitted', 'proof_uploaded', 'partial'])->count(),
            'partial'    => $payments->where('status', 'partial')->count(),
            'outstanding'=> (float) $payments->sum(fn ($p) => (float) ($p['balance'] ?? 0)),
        ];

        return $this->inertia('School/Payments/Index', compact('payments', 'summary'));
    }

    public function export(Request $request, SchoolPaymentHistoryService $history, CsvExportDispatcher $exports)
    {
        $rows = $history->rowsForSchool($this->school);
        $filename = 'school-payments-'.($this->school->school_prefix ?: $this->school->id).'.csv';

        return $exports->dispatch(
            $request->user(),
            'school_payments',
            $filename,
            $rows,
            ['Type', 'Label', 'Level', 'Amount', 'Paid', 'Balance', 'Status', 'Payment date', 'Receipt #', 'Email status', 'Transaction ref'],
            fn (array $p) => [
                $p['type'],
                $p['label'],
                $p['level_label'] ?? '',
                $p['amount'],
                $p['amount_paid'] ?? '',
                $p['balance'] ?? '',
                $p['status'],
                $p['payment_date'] ?? '',
                $p['receipt_number'] ?? '',
                $p['receipt_email_status'] ?? '',
                $p['transaction_ref'] ?? '',
            ],
        );
    }

    public function membershipReceipt(string $tenantId, MembershipPayment $payment, MembershipReceiptService $receiptService)
    {
        abort_if($payment->school_id !== $this->school->id, 403);
        abort_unless($payment->status === 'verified', 404, 'Receipt not available for unverified payments.');

        $payment->loadMissing('feeReceipt');

        if (! $payment->feeReceipt?->generated_receipt_path) {
            $receiptService->issueForPayment($payment->fresh());
            $payment->refresh();
        }

        $html = $payment->feeReceipt ? $receiptService->readGeneratedReceipt($payment->feeReceipt) : null;
        abort_if(! $html, 404, 'Receipt not yet generated.');

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function programReceipt(string $tenantId, FeeReceipt $feeReceipt, ProgramFeeReceiptService $receiptService)
    {
        abort_if($receiptService->schoolIdForReceipt($feeReceipt) !== $this->school->id, 403);
        abort_unless($feeReceipt->status === 'approved', 404, 'Receipt not available until payment is approved.');

        $html = $receiptService->readOrGenerate($feeReceipt);
        abort_if(! $html, 404, 'Receipt not yet generated.');

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
