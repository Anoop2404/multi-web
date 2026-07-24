<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FeeReceipt;
use App\Models\FestFeeCredit;
use App\Models\MembershipPayment;
use App\Models\ProgramFeeCredit;
use App\Services\Exports\CsvExportDispatcher;
use App\Services\Fees\CreditNoteService;
use App\Services\Fees\ProgramFeeReceiptService;
use App\Services\Fees\SchoolPaymentHistoryService;
use App\Services\Membership\MembershipReceiptService;
use Illuminate\Http\Request;

class PaymentHistoryController extends SchoolAdminController
{
    public function index(SchoolPaymentHistoryService $history)
    {
        $payments = $history->rowsForSchool($this->school);

        // NOTE on 'total' below: for fest/training-batch/mcq-batch rows, 'amount' is
        // total_due (what's owed), not what was actually paid — and it isn't filtered by
        // status, so rejected/pending rows count toward it too. Kept as-is (labeled "Total
        // recorded" in Index.vue, which is accurate for what it actually is) rather than
        // changed, since other code may read this key — but it should never be read as
        // "total paid". 'total_paid' below is what that figure actually needs to be, and
        // is now surfaced as its own card alongside 'outstanding' (both existed on this
        // summary already but 'outstanding' was previously computed and never displayed).
        // See docs/CROSS_SYSTEM_FLOW_GAP_AUDIT.md §6b P5.
        $summary = [
            'total'      => (float) $payments->sum('amount'),
            'total_paid' => (float) $payments->sum(fn ($p) => (float) ($p['amount_paid'] ?? (
                in_array($p['status'] ?? null, ['verified', 'approved'], true) ? ($p['amount'] ?? 0) : 0
            ))),
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
            ['Type', 'Label', 'Level', 'Amount', 'Paid', 'Balance', 'Credit owed', 'Status', 'Payment date', 'Receipt #', 'Email status', 'Transaction ref'],
            fn (array $p) => [
                $p['type'],
                $p['label'],
                $p['level_label'] ?? '',
                $p['amount'],
                $p['amount_paid'] ?? '',
                $p['balance'] ?? '',
                // See docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §14 — fest-only, 0/blank elsewhere.
                $p['available_credit'] ?? '',
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

        $html = $receiptService->readOrGenerateForPayment($payment);
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

    public function programProof(string $tenantId, FeeReceipt $feeReceipt, ProgramFeeReceiptService $receiptService)
    {
        abort_if($receiptService->schoolIdForReceipt($feeReceipt) !== $this->school->id, 403);
        abort_if($feeReceipt->isSystemCredit() || ! $feeReceipt->file_path, 404, 'No payment proof uploaded.');

        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        abort_unless($disk->exists($feeReceipt->file_path), 404, 'Payment proof file not found.');

        return response()->file($disk->path($feeReceipt->file_path));
    }

    /**
     * Serves a credit note (see docs/FLOW_GAP_FIX_PLAN.md Phase 3b.2) — $type is 'fest' or
     * 'program', matching SchoolPaymentHistoryService::creditNoteUrl()'s two link shapes.
     */
    public function creditNote(string $tenantId, string $type, int $creditId, CreditNoteService $notes)
    {
        abort_unless(in_array($type, ['fest', 'program'], true), 404);

        $credit = $type === 'fest'
            ? FestFeeCredit::findOrFail($creditId)
            : ProgramFeeCredit::findOrFail($creditId);

        abort_if($notes->schoolIdForCredit($credit) !== $this->school->id, 403);

        $html = $notes->readOrGenerate($credit);
        abort_if(! $html, 404, 'Credit note not available.');

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
