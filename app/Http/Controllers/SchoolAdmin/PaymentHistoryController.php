<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FeeReceipt;
use App\Models\FestEvent;
use App\Models\FestSchoolEventFee;
use App\Models\MembershipPayment;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Models\TrainingRegistration;
use App\Services\Fees\ProgramFeeReceiptService;
use App\Services\Membership\MembershipReceiptService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentHistoryController extends SchoolAdminController
{
    private function festProgramSlug(?FestEvent $event): string
    {
        return match ($event?->event_type) {
            'sports'    => 'sports-meet',
            'kids_fest' => 'kids-fest',
            default     => 'kalotsav',
        };
    }

    private function programReceiptUrl(?FeeReceipt $receipt): ?string
    {
        if (! $receipt || $receipt->status !== 'approved') {
            return null;
        }

        return "/school-admin/{$this->school->id}/payments/receipts/{$receipt->id}";
    }

    public function index()
    {
        $payments = $this->paymentRows();

        $summary = [
            'total'      => (float) $payments->sum('amount'),
            'membership' => (float) $payments->where('type', 'membership')->sum('amount'),
            'fest'       => (float) $payments->where('type', 'fest')->sum('amount'),
            'training'   => (float) $payments->where('type', 'training')->sum('amount'),
            'mcq'        => (float) $payments->where('type', 'mcq')->sum('amount'),
            'approved'   => $payments->whereIn('status', ['verified', 'approved'])->count(),
            'pending'    => $payments->whereIn('status', ['pending', 'uploaded', 'submitted', 'proof_uploaded'])->count(),
        ];

        return $this->inertia('School/Payments/Index', compact('payments', 'summary'));
    }

    public function export(): StreamedResponse
    {
        $rows = $this->paymentRows();
        $filename = 'school-payments-'.($this->school->school_prefix ?: $this->school->id).'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Type', 'Label', 'Level', 'Amount', 'Status', 'Payment date', 'Receipt #', 'Transaction ref']);
            foreach ($rows as $p) {
                fputcsv($out, [
                    $p['type'],
                    $p['label'],
                    $p['level_label'] ?? '',
                    $p['amount'],
                    $p['status'],
                    $p['payment_date'] ?? '',
                    $p['receipt_number'] ?? '',
                    $p['transaction_ref'] ?? '',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function paymentRows()
    {
        $membership = MembershipPayment::where('school_id', $this->school->id)
            ->with('feeReceipt')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($p) => [
                'id'              => $p->id,
                'type'            => 'membership',
                'label'           => 'Membership Fee — '.$p->academic_year,
                'level_label'     => null,
                'amount'          => $p->amount,
                'status'          => $p->status,
                'payment_date'    => $p->verified_at?->toDateString() ?? $p->created_at->toDateString(),
                'transaction_ref' => $p->transaction_ref,
                'receipt_number'  => $p->feeReceipt?->receipt_number,
                'receipt_url'     => $p->status === 'verified'
                    ? "/school-admin/{$this->school->id}/payments/membership/{$p->id}/receipt"
                    : null,
                'rejection_reason' => null,
            ]);

        $fest = FestSchoolEventFee::where('school_id', $this->school->id)
            ->with(['feeReceipt', 'event'])
            ->get()
            ->map(fn ($f) => [
                'id'               => $f->id,
                'type'             => 'fest',
                'label'            => ($f->event?->title ?? 'Fest').' — event fee',
                'level_label'      => $f->event ? config("fest_fees.level_labels.{$f->event->level_round}", $f->event->level_round) : null,
                'amount'           => $f->total_due,
                'status'           => $f->status === 'approved' ? 'approved' : ($f->status === 'proof_uploaded' ? 'uploaded' : $f->status),
                'payment_date'     => $f->feeReceipt?->payment_date?->toDateString(),
                'transaction_ref'  => $f->feeReceipt?->transaction_ref,
                'receipt_number'   => $f->feeReceipt?->receipt_number,
                'receipt_url'      => $f->status === 'approved' && $f->event
                    ? "/school-admin/{$this->school->id}/programs/{$this->festProgramSlug($f->event)}/events/{$f->event_id}/receipt"
                    : null,
                'rejection_reason' => $f->feeReceipt?->status === 'rejected' ? $f->feeReceipt->rejection_reason : null,
            ]);

        $training = TrainingRegistration::where('school_id', $this->school->id)
            ->whereNotNull('fee_receipt_id')
            ->with(['feeReceipt', 'program', 'teacher'])
            ->get()
            ->map(fn ($r) => [
                'id'               => $r->id,
                'type'             => 'training',
                'label'            => ($r->program?->title ?? 'Training').' — '.$r->teacher?->name,
                'level_label'      => null,
                'amount'           => $r->feeReceipt?->amount ?? $r->program?->fee_amount,
                'status'           => $r->feeReceipt?->status === 'approved' ? 'approved' : ($r->feeReceipt?->status ?? 'pending'),
                'payment_date'     => $r->feeReceipt?->payment_date?->toDateString(),
                'transaction_ref'  => $r->feeReceipt?->transaction_ref,
                'receipt_number'   => $r->feeReceipt?->receipt_number,
                'receipt_url'      => $this->programReceiptUrl($r->feeReceipt),
                'rejection_reason' => $r->feeReceipt?->status === 'rejected' ? $r->feeReceipt->rejection_reason : null,
            ]);

        $mcqBatch = McqSchoolFee::where('school_id', $this->school->id)
            ->whereNotNull('fee_receipt_id')
            ->with(['feeReceipt', 'exam'])
            ->get()
            ->map(fn ($f) => [
                'id'               => 'batch-'.$f->id,
                'type'             => 'mcq',
                'label'            => ($f->exam?->title ?? 'MCQ').' — batch fee ('.$f->student_count.' students)',
                'level_label'      => $f->exam?->exam_level ? 'Level '.$f->exam->exam_level : null,
                'amount'           => $f->total_due,
                'status'           => $f->status === 'approved' ? 'approved' : ($f->status === 'proof_uploaded' ? 'uploaded' : $f->status),
                'payment_date'     => $f->feeReceipt?->payment_date?->toDateString(),
                'transaction_ref'  => $f->feeReceipt?->transaction_ref,
                'receipt_number'   => $f->feeReceipt?->receipt_number,
                'receipt_url'      => $this->programReceiptUrl($f->feeReceipt),
                'rejection_reason' => $f->feeReceipt?->status === 'rejected' ? $f->feeReceipt->rejection_reason : null,
            ]);

        $mcq = McqRegistration::where('school_id', $this->school->id)
            ->whereNotNull('fee_receipt_id')
            ->with(['feeReceipt', 'exam', 'student'])
            ->get()
            ->map(fn ($r) => [
                'id'               => $r->id,
                'type'             => 'mcq',
                'label'            => ($r->exam?->title ?? 'MCQ').' — '.$r->student?->name,
                'level_label'      => null,
                'amount'           => $r->feeReceipt?->amount ?? $r->exam?->fee_amount,
                'status'           => $r->feeReceipt?->status === 'approved' ? 'approved' : ($r->feeReceipt?->status ?? 'pending'),
                'payment_date'     => $r->feeReceipt?->payment_date?->toDateString(),
                'transaction_ref'  => $r->feeReceipt?->transaction_ref,
                'receipt_number'   => $r->feeReceipt?->receipt_number,
                'receipt_url'      => $this->programReceiptUrl($r->feeReceipt),
                'rejection_reason' => $r->feeReceipt?->status === 'rejected' ? $r->feeReceipt->rejection_reason : null,
            ]);

        return $membership
            ->concat($fest)
            ->concat($training)
            ->concat($mcqBatch)
            ->concat($mcq)
            ->sortByDesc('payment_date')
            ->values();
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
