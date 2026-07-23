<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FeeReceipt;
use App\Models\FestSchoolEventFee;
use App\Models\MembershipPayment;
use App\Models\McqSchoolFee;
use App\Models\Tenant;
use App\Models\TrainingRegistration;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Fees\FeeReceiptEmailTracker;
use App\Services\Fees\OfflineProgramFeeOrchestrator;
use App\Services\Exports\CsvExportDispatcher;
use App\Services\Fees\ProgramFeeReceiptService;
use App\Services\Fees\SchoolPaymentHistoryService;
use App\Services\Ledger\FeeReceiptReversalService;
use App\Services\Membership\MembershipNotifier;
use App\Services\Membership\MembershipReceiptService;
use Illuminate\Http\Request;

class UnifiedPaymentsController extends SahodayaAdminController
{
    public function index(Request $request, SchoolPaymentHistoryService $history)
    {
        $filters = $request->validate([
            'type'      => 'nullable|in:all,membership,fest,training,mcq',
            'status'    => 'nullable|string|max:40',
            'school_id' => 'nullable|string',
            'search'    => 'nullable|string|max:100',
        ]);

        $schools = Tenant::query()
            ->where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->orderBy('name')
            ->get(['id', 'name']);

        $rows = $history->rowsForSahodaya($this->sahodaya);

        if (($filters['type'] ?? 'all') !== 'all') {
            $rows = $rows->where('type', $filters['type']);
        }

        if (! empty($filters['school_id'])) {
            $rows = $rows->where('school_id', $filters['school_id']);
        }

        if (! empty($filters['status'])) {
            $rows = $rows->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $term = strtolower($filters['search']);
            $rows = $rows->filter(fn (array $row) => str_contains(strtolower($row['label'] ?? ''), $term)
                || str_contains(strtolower($row['school_name'] ?? ''), $term)
                || str_contains(strtolower($row['receipt_number'] ?? ''), $term));
        }

        $summary = [
            'total'      => (float) $rows->sum('amount'),
            'membership' => (float) $rows->where('type', 'membership')->sum('amount'),
            'fest'       => (float) $rows->where('type', 'fest')->sum('amount'),
            'training'   => (float) $rows->where('type', 'training')->sum('amount'),
            'mcq'        => (float) $rows->where('type', 'mcq')->sum('amount'),
            'approved'   => $rows->whereIn('status', ['verified', 'approved'])->count(),
            'pending'    => $rows->whereIn('status', ['pending', 'uploaded', 'submitted', 'proof_uploaded'])->count(),
            // Fest-only — see docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §14.
            'fest_credit' => (float) $rows->sum('available_credit'),
        ];

        return $this->inertia('Sahodaya/Finance/UnifiedPayments', [
            'payments' => $rows->values(),
            'summary'  => $summary,
            'schools'  => $schools,
            'filters'  => array_merge([
                'type'      => 'all',
                'status'    => '',
                'school_id' => '',
                'search'    => '',
            ], $filters),
        ]);
    }

    public function export(Request $request, SchoolPaymentHistoryService $history, CsvExportDispatcher $exports)
    {
        $rows = $history->rowsForSahodaya($this->sahodaya);
        $filename = 'sahodaya-payments-'.$this->sahodaya->id.'.csv';
        $headers = ['School', 'Type', 'Label', 'Amount', 'Credit owed', 'Status', 'Payment date', 'Receipt #', 'Email status', 'Transaction ref'];

        return $exports->dispatch(
            $request->user(),
            'sahodaya_unified_payments',
            $filename,
            $rows,
            $headers,
            fn (array $p) => [
                $p['school_name'] ?? '',
                $p['type'],
                $p['label'],
                $p['amount'],
                // Fest-only, see docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §14.
                $p['available_credit'] ?? '',
                $p['status'],
                $p['payment_date'] ?? '',
                $p['receipt_number'] ?? '',
                $p['receipt_email_status'] ?? '',
                $p['transaction_ref'] ?? '',
            ],
        );
    }

    public function programReceipt(string $tenantId, FeeReceipt $feeReceipt, ProgramFeeReceiptService $receiptService)
    {
        $schoolId = $receiptService->schoolIdForReceipt($feeReceipt);
        abort_unless($schoolId && Tenant::find($schoolId)?->parent_id === $this->sahodaya->id, 403);
        abort_unless($feeReceipt->status === 'approved', 404);

        $html = $receiptService->readOrGenerate($feeReceipt);
        abort_if(! $html, 404, 'Receipt not yet generated.');

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function reverseReceipt(Request $request, string $tenantId, FeeReceipt $feeReceipt, FeeReceiptReversalService $reversals, ProgramFeeReceiptService $receiptService)
    {
        $schoolId = $receiptService->schoolIdForReceipt($feeReceipt);
        abort_unless($schoolId && Tenant::find($schoolId)?->parent_id === $this->sahodaya->id, 403);

        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $reversals->reverse($feeReceipt, $request->user(), $data['reason'] ?? null);

        return back()->with('success', 'Fee receipt reversed and compensating ledger entries posted.');
    }

    public function resendReceipt(
        Request $request,
        string $tenantId,
        MembershipNotifier $notifier,
        OfflineProgramFeeOrchestrator $orchestrator,
        MembershipReceiptService $membershipReceipts,
        FeeReceiptEmailTracker $tracker,
        PlatformAuditLogger $audit,
    ) {
        $data = $request->validate([
            'type'           => 'required|in:membership,fest,training,mcq',
            'id'             => 'required|string',
            'fee_receipt_id' => 'nullable|integer',
        ]);

        if ($data['type'] === 'membership') {
            $payment = MembershipPayment::with(['school', 'feeReceipt', 'registration'])->findOrFail($data['id']);
            abort_if($payment->school?->parent_id !== $this->sahodaya->id, 403);
            abort_unless($payment->status === 'verified', 422, 'Receipt can only be resent for verified payments.');

            $receipt = $payment->feeReceipt;
            if (! $receipt?->generated_receipt_path) {
                $membershipReceipts->issueForPayment($payment->fresh());
                $receipt = $payment->fresh()->feeReceipt;
            }

            abort_if(! $receipt, 422, 'Receipt not available.');

            $tracker->incrementResend($receipt);
            $tracker->markQueued($receipt);

            try {
                $html = $membershipReceipts->readGeneratedReceipt($receipt);
                $notifier->registrationCompleted(
                    $payment->school,
                    $payment->academic_year,
                    $payment->registration?->reg_no ?? '—',
                    false,
                    $html,
                    $receipt->receipt_number,
                );
                $tracker->markSent($receipt->fresh());
            } catch (\Throwable $e) {
                $tracker->markFailed($receipt->fresh(), $e->getMessage());
                throw $e;
            }

            $audit->log('receipt.email.resent', "Membership receipt resent for {$payment->school?->name}", $receipt);
        } else {
            $receipt = FeeReceipt::findOrFail($data['fee_receipt_id'] ?? 0);
            abort_unless($receipt->status === 'approved', 422);

            $school = $this->resolveSchoolForReceipt($data['type'], $data['id'], $receipt);
            abort_if($school->parent_id !== $this->sahodaya->id, 403);

            $tracker->incrementResend($receipt);
            $context = $this->receiptContext($data['type'], $data['id'], $receipt);

            $orchestrator->notifyApproved(
                $school,
                $receipt,
                $context['fee_type'],
                $context['title'],
            );

            $audit->log('receipt.email.resent', "Program receipt resent for {$school->name}", $receipt);
        }

        return back()->with('success', 'Receipt email queued successfully.');
    }

    private function resolveSchoolForReceipt(string $type, string $id, FeeReceipt $receipt): Tenant
    {
        return match ($type) {
            'fest' => Tenant::findOrFail(
                FestSchoolEventFee::findOrFail($id)->school_id
            ),
            'training' => Tenant::findOrFail(
                str_starts_with($id, 'training-batch-')
                    ? \App\Models\TrainingSchoolFee::findOrFail((int) str_replace('training-batch-', '', $id))->school_id
                    : TrainingRegistration::findOrFail($id)->school_id
            ),
            'mcq' => Tenant::findOrFail(
                str_starts_with($id, 'batch-')
                    ? McqSchoolFee::findOrFail((int) str_replace('batch-', '', $id))->school_id
                    : \App\Models\McqRegistration::findOrFail($id)->school_id
            ),
            default => abort(422, 'Unsupported payment type.'),
        };
    }

    /** @return array{fee_type: string, title: string} */
    private function receiptContext(string $type, string $id, FeeReceipt $receipt): array
    {
        return match ($type) {
            'fest' => (function () use ($id) {
                $fee = FestSchoolEventFee::with('event')->findOrFail($id);

                return [
                    'fee_type' => 'Event fee',
                    'title'    => $fee->event?->title ?? 'Fest event',
                ];
            })(),
            'training' => (function () use ($id) {
                if (str_starts_with($id, 'training-batch-')) {
                    $fee = \App\Models\TrainingSchoolFee::with('program')->findOrFail((int) str_replace('training-batch-', '', $id));

                    return [
                        'fee_type' => 'Training batch fee',
                        'title'    => $fee->program?->title ?? 'Training program',
                    ];
                }
                $reg = TrainingRegistration::with('program')->findOrFail($id);

                return [
                    'fee_type' => 'Training fee',
                    'title'    => $reg->program?->title ?? 'Training program',
                ];
            })(),
            'mcq' => (function () use ($id) {
                if (str_starts_with($id, 'batch-')) {
                    $fee = McqSchoolFee::with('exam')->findOrFail((int) str_replace('batch-', '', $id));

                    return [
                        'fee_type' => 'Talent Search fee',
                        'title'    => $fee->exam?->title ?? 'Talent Search exam',
                    ];
                }
                $reg = \App\Models\McqRegistration::with('exam')->findOrFail($id);

                return [
                    'fee_type' => 'Talent Search fee',
                    'title'    => $reg->exam?->title ?? 'Talent Search exam',
                ];
            })(),
            default => abort(422),
        };
    }
}
