<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FeeReceipt;
use App\Models\FestFeeCredit;
use App\Models\FestSchoolEventFee;
use App\Models\MembershipPayment;
use App\Models\McqSchoolFee;
use App\Models\ProgramFeeCredit;
use App\Models\Tenant;
use App\Models\TrainingRegistration;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Fees\CreditNoteService;
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
        $request->merge([
            'show_all' => filter_var($request->input('show_all'), FILTER_VALIDATE_BOOLEAN),
        ]);

        $filters = $request->validate([
            'type'      => 'nullable|in:all,membership,fest,training,mcq',
            'status'    => 'nullable|string|max:40',
            'school_id' => 'nullable|string',
            'search'    => 'nullable|string|max:100',
            'from_date' => 'nullable|date',
            'to_date'   => 'nullable|date',
            'show_all'  => 'nullable',
        ]);

        $schools = Tenant::query()
            ->where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->orderBy('name')
            ->get(['id', 'name']);

        // A Sahodaya with 100 schools can accumulate years of receipts across all four
        // programs — loading all of it on every page view of this screen doesn't scale
        // (see docs/SCALE_AND_PAGINATION_PLAN.md §3). Default to the current academic
        // year unless the admin explicitly asks for `from_date`/`to_date` or ticks
        // "show all history"; type/school_id are pushed into the query too instead of
        // filtering the fully-fetched collection afterward.
        $defaultFrom = $this->defaultHistoryFromDate();
        $fromDate = $filters['show_all'] ?? false
            ? ($filters['from_date'] ?? null)
            : ($filters['from_date'] ?? $defaultFrom?->toDateString());

        $rows = $history->rowsForSahodaya($this->sahodaya, [
            'type'      => $filters['type'] ?? 'all',
            'school_id' => $filters['school_id'] ?? null,
            'from'      => $fromDate,
            'to'        => $filters['to_date'] ?? null,
        ]);

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
                'from_date' => '',
                'to_date'   => '',
                'show_all'  => false,
            ], $filters, [
                // Echo back what was actually applied (including the computed default)
                // so the frontend can show it in the date field rather than a blank box
                // that silently isn't "all time".
                'from_date' => $fromDate ?? '',
            ]),
        ]);
    }

    /**
     * Default lower bound for the unified payments screen: the active academic year's
     * start date if one is configured, else the Apr–Mar calendar academic year. Only
     * used when the admin hasn't explicitly set from_date or ticked "show all history".
     */
    private function defaultHistoryFromDate(): ?\Illuminate\Support\Carbon
    {
        $active = \App\Support\AcademicYear::activeRecord();
        if ($active?->start_date) {
            return \Illuminate\Support\Carbon::parse($active->start_date);
        }

        $now = now();
        $year = $now->month >= 4 ? $now->year : $now->year - 1;

        return \Illuminate\Support\Carbon::create($year, 4, 1)->startOfDay();
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

        $schoolId = $notes->schoolIdForCredit($credit);
        abort_unless($schoolId && Tenant::find($schoolId)?->parent_id === $this->sahodaya->id, 403);

        $html = $notes->readOrGenerate($credit);
        abort_if(! $html, 404, 'Credit note not available.');

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

    public function proof(string $tenantId, FeeReceipt $feeReceipt)
    {
        abort_if($feeReceipt->isSystemCredit() || ! $feeReceipt->file_path, 404, 'No payment proof uploaded.');

        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        abort_unless($disk->exists($feeReceipt->file_path), 404, 'Payment proof file not found.');

        return response()->file($disk->path($feeReceipt->file_path));
    }

    public function recordCreditPayout(Request $request, \App\Services\Fees\CreditPayoutService $payoutService)
    {
        $data = $request->validate([
            'credit_type' => 'required|in:fest,program',
            'credit_id'   => 'required|integer',
            'bank_ref'    => 'nullable|string|max:255',
            'notes'       => 'nullable|string|max:1000',
        ]);

        $credit = $data['credit_type'] === 'fest'
            ? \App\Models\FestFeeCredit::findOrFail($data['credit_id'])
            : \App\Models\ProgramFeeCredit::findOrFail($data['credit_id']);

        $payoutService->recordPayout($credit, $request->user(), $data['bank_ref'] ?? null, $data['notes'] ?? null);

        return back()->with('success', 'Out-of-platform bank payout recorded. Credit closed out.');
    }

    public function creditsReport(Request $request)
    {
        $schoolIds = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->pluck('id');

        $schoolNames = Tenant::whereIn('id', $schoolIds)->pluck('name', 'id');

        $festCredits = \App\Models\FestFeeCredit::whereHas('schoolEventFee', fn ($q) => $q->whereIn('school_id', $schoolIds))
            ->with(['schoolEventFee.event', 'sourceRegistration', 'createdBy:id,name'])
            ->latest('id')
            ->get()
            ->map(fn (\App\Models\FestFeeCredit $c) => [
                'id'            => $c->id,
                'credit_type'   => 'fest',
                'school_id'     => $c->schoolEventFee?->school_id,
                'school_name'   => $schoolNames->get($c->schoolEventFee?->school_id),
                'amount'        => (float) $c->amount,
                'reason'        => $c->reason,
                'source_label'  => ($c->schoolEventFee?->event?->title ?? 'Fest').' — Event Fee',
                'created_at'    => $c->created_at?->toIso8601String(),
                'applied_at'    => $c->applied_at?->toIso8601String(),
                'status'        => $c->applied_at ? 'closed' : 'outstanding',
                'created_by'    => $c->createdBy?->name,
            ]);

        // creditable_type is McqSchoolFee/TrainingSchoolFee for program (MCQ/Training) fee
        // credits, but Tenant::class directly for a membership cancel-with-settlement credit
        // (SchoolMembershipCancellationService::cancelWithSettlement() — see
        // docs/FLOW_GAP_FIX_PLAN.md Phase 1.4/D4) since a membership credit isn't tied to any
        // per-program fee row, the school itself is the creditable. Resolve both shapes here
        // so membership credits don't silently disappear from this report.
        $programCreditSchoolId = fn (\App\Models\ProgramFeeCredit $c) => $c->creditable instanceof Tenant
            ? $c->creditable->id
            : $c->creditable?->school_id;

        $programCredits = \App\Models\ProgramFeeCredit::with(['creditable', 'source', 'createdBy:id,name'])
            ->latest('id')
            ->get()
            ->filter(fn (\App\Models\ProgramFeeCredit $c) => in_array($programCreditSchoolId($c), $schoolIds->all(), true))
            ->map(function (\App\Models\ProgramFeeCredit $c) use ($programCreditSchoolId, $schoolNames) {
                $schoolId = $programCreditSchoolId($c);

                return [
                    'id'            => $c->id,
                    'credit_type'   => 'program',
                    'school_id'     => $schoolId,
                    'school_name'   => $schoolNames->get($schoolId),
                    'amount'        => (float) $c->amount,
                    'reason'        => $c->reason,
                    'source_label'  => match ($c->creditable_type) {
                        \App\Models\McqSchoolFee::class => 'Talent Search Batch Fee',
                        \App\Models\TrainingSchoolFee::class => 'Training Program Fee',
                        Tenant::class => 'Membership',
                        default => 'Programme Fee',
                    },
                    'created_at'    => $c->created_at?->toIso8601String(),
                    'applied_at'    => $c->applied_at?->toIso8601String(),
                    'status'        => $c->applied_at ? 'closed' : 'outstanding',
                    'created_by'    => $c->createdBy?->name,
                ];
            });

        $payouts = \App\Models\CreditPayout::whereIn('school_id', $schoolIds)
            ->with(['recordedBy:id,name'])
            ->latest('id')
            ->get()
            ->map(fn (\App\Models\CreditPayout $p) => [
                'id'          => $p->id,
                'school_name' => $schoolNames->get($p->school_id),
                'amount'      => (float) $p->amount,
                'bank_ref'    => $p->bank_ref,
                'notes'       => $p->notes,
                'created_at'  => $p->created_at?->toIso8601String(),
                'recorded_by' => $p->recordedBy?->name,
            ]);

        $credits = $festCredits->concat($programCredits)->sortByDesc('created_at')->values();

        $stats = [
            'total_issued' => (float) $credits->sum('amount'),
            'outstanding'  => (float) $credits->where('status', 'outstanding')->sum('amount'),
            'closed'       => (float) $credits->where('status', 'closed')->sum('amount'),
            'paid_out'     => (float) $payouts->sum('amount'),
        ];

        return $this->inertia('Sahodaya/Finance/CreditsReport', [
            'credits' => $credits,
            'payouts' => $payouts->values(),
            'stats'   => $stats,
        ]);
    }
}
