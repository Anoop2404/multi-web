<?php

namespace App\Services\Fees;

use App\Models\FestEvent;
use App\Models\FestFeeCredit;
use App\Models\FestSchoolEventFee;
use App\Models\MembershipPayment;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Models\ProgramFeeCredit;
use App\Models\Tenant;
use App\Models\TrainingRegistration;
use App\Models\TrainingSchoolFee;
use App\Models\FeeReceipt;
use App\Support\TenancyDatabase;
use Illuminate\Support\Collection;

class SchoolPaymentHistoryService
{
    /**
     * @param  array{from?: ?string, to?: ?string, school_id?: ?string, type?: ?string}  $filters
     */
    public function rowsForSchool(Tenant $school, array $filters = []): Collection
    {
        return $this->buildRows(collect([$school]), $school->id, null, $filters);
    }

    /**
     * @param  array{from?: ?string, to?: ?string, school_id?: ?string, type?: ?string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function rowsForSahodaya(Tenant $sahodaya, array $filters = []): Collection
    {
        $schools = Tenant::query()
            ->where('parent_id', $sahodaya->id)
            ->where('type', 'school')
            ->orderBy('name')
            ->get(['id', 'name']);

        return $this->buildRows($schools, null, $sahodaya->id, $filters);
    }

    /**
     * Every sub-query below is scoped by an optional date range and skipped entirely
     * when a `type` filter narrows the request to a different program — previously
     * all six ran unconditionally and unbounded for every Sahodaya-wide page view or
     * report, regardless of what the caller actually asked to see.
     * See docs/SCALE_AND_PAGINATION_PLAN.md §3 (Option A).
     *
     * @param  Collection<int, Tenant>  $schools
     * @param  array{from?: ?string, to?: ?string, school_id?: ?string, type?: ?string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function buildRows(Collection $schools, ?string $urlSchoolId, ?string $sahodayaId = null, array $filters = []): Collection
    {
        $schoolIds = $schools->pluck('id');
        $schoolNames = $schools->pluck('name', 'id');

        if (! empty($filters['school_id'])) {
            $schoolIds = $schoolIds->filter(fn ($id) => (string) $id === (string) $filters['school_id'])->values();
        }

        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $type = $filters['type'] ?? 'all';

        $membership = ($type === 'all' || $type === 'membership')
            ? $this->dateScope(MembershipPayment::whereIn('school_id', $schoolIds)->with('feeReceipt.reviewedBy'), $from, $to)
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (MembershipPayment $p) => $this->mapMembershipRow($p, $schoolNames, $urlSchoolId, $sahodayaId))
            : collect();

        $fest = ($type === 'all' || $type === 'fest')
            ? $this->dateScope(FestSchoolEventFee::whereIn('school_id', $schoolIds)->with(['feeReceipt.reviewedBy', 'event']), $from, $to)
                ->get()
                ->map(fn (FestSchoolEventFee $f) => $this->mapFestRow($f, $schoolNames, $urlSchoolId, $sahodayaId))
            : collect();

        $training = ($type === 'all' || $type === 'training')
            ? $this->dateScope(TrainingRegistration::whereIn('school_id', $schoolIds)->whereNotNull('fee_receipt_id')->with(['feeReceipt.reviewedBy', 'program', 'teacher']), $from, $to)
                ->get()
                ->map(fn (TrainingRegistration $r) => $this->mapTrainingRow($r, $schoolNames, $urlSchoolId, $sahodayaId))
            : collect();

        $trainingBatch = ($type === 'all' || $type === 'training')
            ? $this->dateScope(TrainingSchoolFee::whereIn('school_id', $schoolIds)->whereNotNull('fee_receipt_id')->with(['feeReceipt.reviewedBy', 'program']), $from, $to)
                ->get()
                ->map(fn (TrainingSchoolFee $f) => $this->mapTrainingBatchRow($f, $schoolNames, $urlSchoolId, $sahodayaId))
            : collect();

        $mcqBatch = ($type === 'all' || $type === 'mcq')
            ? $this->dateScope(McqSchoolFee::whereIn('school_id', $schoolIds)->whereNotNull('fee_receipt_id')->with(['feeReceipt.reviewedBy', 'exam']), $from, $to)
                ->get()
                ->map(fn (McqSchoolFee $f) => $this->mapMcqBatchRow($f, $schoolNames, $urlSchoolId, $sahodayaId))
            : collect();

        $mcq = ($type === 'all' || $type === 'mcq')
            ? $this->dateScope(McqRegistration::whereIn('school_id', $schoolIds)->whereNotNull('fee_receipt_id')->with(['feeReceipt.reviewedBy', 'exam', 'student']), $from, $to)
                ->get()
                ->map(fn (McqRegistration $r) => $this->mapMcqRow($r, $schoolNames, $urlSchoolId, $sahodayaId))
            : collect();

        // Sort by the actual review/approval timestamp, not payment_date — payment_date is
        // date-only (no time component) and, for fest/training/mcq rows, is the bank
        // transaction date the school typed in, which has no relationship to receipt
        // ordering. Receipt numbers are assigned at approval time (see
        // SahodayaReceiptNumberAllocator), so reviewed_at is what actually determines
        // "correct order" — sorting by it keeps the displayed order consistent with the
        // receipt numbers themselves instead of falling back to arbitrary insertion order
        // whenever two rows share the same payment_date. Rows with no reviewed_at yet
        // (still pending/uploaded) fall back to payment_date so they still get a sensible
        // position in the list.
        return $membership
            ->concat($fest)
            ->concat($training)
            ->concat($trainingBatch)
            ->concat($mcqBatch)
            ->concat($mcq)
            ->sortByDesc(fn (array $row) => $row['reviewed_at'] ?? $row['payment_date'])
            ->values();
    }

    /**
     * Bounds a sub-query to `created_at` between $from/$to when given. Applied before
     * any of the six queries run, not to the merged collection afterward — see the
     * class-level note on buildRows().
     */
    private function dateScope($query, ?string $from, ?string $to)
    {
        return $query
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));
    }

    /** @param  Collection<string, string>  $schoolNames */
    private function mapMembershipRow(
        MembershipPayment $p,
        Collection $schoolNames,
        ?string $urlSchoolId,
        ?string $sahodayaId,
    ): array {
        $schoolId = $p->school_id;

        return [
            'id'                   => $p->id,
            'type'                 => 'membership',
            'school_id'            => $schoolId,
            'school_name'          => $schoolNames->get($schoolId),
            'label'                => 'Membership Fee — '.$p->academic_year,
            'level_label'          => null,
            'amount'               => $p->amount,
            'status'               => $p->status,
            'payment_date'         => $p->verified_at?->toDateString() ?? $p->created_at->toDateString(),
            'reviewed_at'          => $p->feeReceipt?->reviewed_at?->toDateTimeString() ?? $p->verified_at?->toDateTimeString(),
            'reviewed_by'          => $p->feeReceipt?->reviewedBy?->name,
            'transaction_ref'      => $p->transaction_ref,
            'receipt_number'       => $p->feeReceipt?->receipt_number,
            'proof_url'            => $this->membershipProofUrl($p, $urlSchoolId),
            'receipt_url'          => $this->membershipReceiptUrl($p, $urlSchoolId, $sahodayaId),
            'receipt_email_status' => $p->feeReceipt?->receipt_email_status,
            'receipt_emailed_at'   => $p->feeReceipt?->receipt_emailed_at?->toDateTimeString(),
            'receipt_email_error'  => $p->feeReceipt?->receipt_email_error,
            'receipt_email_resend_count' => $p->feeReceipt?->receipt_email_resend_count ?? 0,
            'fee_receipt_id'       => $p->feeReceipt?->id,
            'receipt_status'       => $p->feeReceipt?->status,
            'rejection_reason'     => $p->feeReceipt?->rejection_reason ?? $p->rejection_reason,
            'receipts_history'     => $this->mapReceiptsHistory($p, $urlSchoolId, $sahodayaId),
        ];
    }

    /** @param  Collection<string, string>  $schoolNames */
    private function mapFestRow(
        FestSchoolEventFee $f,
        Collection $schoolNames,
        ?string $urlSchoolId,
        ?string $sahodayaId,
    ): array {
        $schoolId = $f->school_id;

        return [
            'id'                   => $f->id,
            'type'                 => 'fest',
            'school_id'            => $schoolId,
            'school_name'          => $schoolNames->get($schoolId),
            'label'                => ($f->event?->title ?? 'Fest').' — event fee',
            'level_label'          => $f->event ? config("fest_fees.level_labels.{$f->event->level_round}", $f->event->level_round) : null,
            'amount'               => $f->total_due,
            'amount_paid'          => (float) $f->amount_paid,
            'balance'              => $f->outstandingBalance(),
            'available_credit'     => $f->outstandingCredit(),
            'status'               => $f->status === 'approved' ? 'approved' : ($f->status === 'proof_uploaded' ? 'uploaded' : $f->status),
            'payment_date'         => $f->feeReceipt?->payment_date?->toDateString(),
            'reviewed_at'          => $f->feeReceipt?->reviewed_at?->toDateTimeString(),
            'reviewed_by'          => $f->feeReceipt?->reviewedBy?->name,
            'transaction_ref'      => $f->feeReceipt?->transaction_ref,
            'receipt_number'       => $f->feeReceipt?->receipt_number,
            'proof_url'            => $this->programProofUrl($f->feeReceipt, $schoolId, $urlSchoolId, $sahodayaId, $f->event, $f->id),
            'receipt_url'          => $this->programReceiptUrl($f->feeReceipt, $schoolId, $urlSchoolId, $sahodayaId, $f->event),
            'receipt_email_status' => $f->feeReceipt?->receipt_email_status,
            'receipt_emailed_at'   => $f->feeReceipt?->receipt_emailed_at?->toDateTimeString(),
            'receipt_email_error'  => $f->feeReceipt?->receipt_email_error,
            'receipt_email_resend_count' => $f->feeReceipt?->receipt_email_resend_count ?? 0,
            'fee_receipt_id'       => $f->feeReceipt?->id,
            'receipt_status'       => $f->feeReceipt?->status,
            'rejection_reason'     => $f->feeReceipt?->status === 'rejected' ? $f->feeReceipt->rejection_reason : null,
            'receipts_history'     => $this->mapReceiptsHistory($f, $urlSchoolId, $sahodayaId, $f->event),
        ];
    }

    /** @param  Collection<string, string>  $schoolNames */
    private function mapTrainingRow(
        TrainingRegistration $r,
        Collection $schoolNames,
        ?string $urlSchoolId,
        ?string $sahodayaId,
    ): array {
        $schoolId = $r->school_id;
        $isCancelled = in_array($r->status, ['cancelled', 'withdrawn'], true);
        $label = ($r->program?->title ?? 'Training').' — '.$r->teacher?->name;
        if ($isCancelled) {
            $label = 'CANCELLED — '.$label;
        }

        return [
            'id'                   => $r->id,
            'type'                 => 'training',
            'school_id'            => $schoolId,
            'school_name'          => $schoolNames->get($schoolId),
            'label'                => $label,
            'level_label'          => null,
            'amount'               => $r->feeTotalDue() ?: ($r->feeReceipt?->amount ?? $r->program?->fee_amount),
            'amount_paid'          => (float) $r->amount_paid,
            'balance'              => $r->outstandingBalance(),
            'status'               => $isCancelled ? 'cancelled' : ($r->fee_status ?: ($r->feeReceipt?->status === 'approved' ? 'approved' : ($r->feeReceipt?->status ?? 'pending'))),
            'is_cancelled'         => $isCancelled,
            'payment_date'         => $r->feeReceipt?->payment_date?->toDateString(),
            'reviewed_at'          => $r->feeReceipt?->reviewed_at?->toDateTimeString(),
            'reviewed_by'          => $r->feeReceipt?->reviewedBy?->name,
            'transaction_ref'      => $r->feeReceipt?->transaction_ref,
            'receipt_number'       => $r->feeReceipt?->receipt_number,
            'proof_url'            => $this->programProofUrl($r->feeReceipt, $schoolId, $urlSchoolId, $sahodayaId),
            'receipt_url'          => $this->programReceiptUrl($r->feeReceipt, $schoolId, $urlSchoolId, $sahodayaId),
            'receipt_email_status' => $r->feeReceipt?->receipt_email_status,
            'receipt_emailed_at'   => $r->feeReceipt?->receipt_emailed_at?->toDateTimeString(),
            'receipt_email_error'  => $r->feeReceipt?->receipt_email_error,
            'receipt_email_resend_count' => $r->feeReceipt?->receipt_email_resend_count ?? 0,
            'fee_receipt_id'       => $r->feeReceipt?->id,
            'receipt_status'       => $r->feeReceipt?->status,
            'rejection_reason'     => $r->feeReceipt?->status === 'rejected' ? $r->feeReceipt->rejection_reason : null,
            'receipts_history'     => $this->mapReceiptsHistory($r, $urlSchoolId, $sahodayaId),
        ];
    }

    /** @param  Collection<string, string>  $schoolNames */
    private function mapTrainingBatchRow(
        TrainingSchoolFee $f,
        Collection $schoolNames,
        ?string $urlSchoolId,
        ?string $sahodayaId,
    ): array {
        $schoolId = $f->school_id;

        return [
            'id'                   => 'training-batch-'.$f->id,
            'type'                 => 'training',
            'school_id'            => $schoolId,
            'school_name'          => $schoolNames->get($schoolId),
            'label'                => ($f->program?->title ?? 'Training').' — batch fee ('.$f->teacher_count.' teachers)',
            'level_label'          => null,
            'amount'               => $f->total_due,
            'amount_paid'          => (float) $f->amount_paid,
            'balance'              => $f->outstandingBalance(),
            'status'               => $f->status === 'approved' ? 'approved' : ($f->status === 'proof_uploaded' ? 'uploaded' : $f->status),
            'payment_date'         => $f->feeReceipt?->payment_date?->toDateString(),
            'reviewed_at'          => $f->feeReceipt?->reviewed_at?->toDateTimeString(),
            'reviewed_by'          => $f->feeReceipt?->reviewedBy?->name,
            'transaction_ref'      => $f->feeReceipt?->transaction_ref,
            'receipt_number'       => $f->feeReceipt?->receipt_number,
            'proof_url'            => $this->programProofUrl($f->feeReceipt, $schoolId, $urlSchoolId, $sahodayaId),
            'receipt_url'          => $this->programReceiptUrl($f->feeReceipt, $schoolId, $urlSchoolId, $sahodayaId),
            'receipt_email_status' => $f->feeReceipt?->receipt_email_status,
            'receipt_emailed_at'   => $f->feeReceipt?->receipt_emailed_at?->toDateTimeString(),
            'receipt_email_error'  => $f->feeReceipt?->receipt_email_error,
            'receipt_email_resend_count' => $f->feeReceipt?->receipt_email_resend_count ?? 0,
            'fee_receipt_id'       => $f->feeReceipt?->id,
            'receipt_status'       => $f->feeReceipt?->status,
            'rejection_reason'     => $f->feeReceipt?->status === 'rejected' ? $f->feeReceipt->rejection_reason : null,
            'receipts_history'     => $this->mapReceiptsHistory($f, $urlSchoolId, $sahodayaId),
        ];
    }

    /** @param  Collection<string, string>  $schoolNames */
    private function mapMcqBatchRow(
        McqSchoolFee $f,
        Collection $schoolNames,
        ?string $urlSchoolId,
        ?string $sahodayaId,
    ): array {
        $schoolId = $f->school_id;

        return [
            'id'                   => 'batch-'.$f->id,
            'type'                 => 'mcq',
            'school_id'            => $schoolId,
            'school_name'          => $schoolNames->get($schoolId),
            'label'                => ($f->exam?->title ?? 'Talent Search').' — batch fee ('.$f->student_count.' students)',
            'level_label'          => $f->exam?->exam_level ? 'Level '.$f->exam->exam_level : null,
            'amount'               => $f->total_due,
            'amount_paid'          => (float) $f->amount_paid,
            'balance'              => $f->outstandingBalance(),
            'status'               => $f->status === 'approved' ? 'approved' : ($f->status === 'proof_uploaded' ? 'uploaded' : $f->status),
            'payment_date'         => $f->feeReceipt?->payment_date?->toDateString(),
            'reviewed_at'          => $f->feeReceipt?->reviewed_at?->toDateTimeString(),
            'reviewed_by'          => $f->feeReceipt?->reviewedBy?->name,
            'transaction_ref'      => $f->feeReceipt?->transaction_ref,
            'receipt_number'       => $f->feeReceipt?->receipt_number,
            'proof_url'            => $this->programProofUrl($f->feeReceipt, $schoolId, $urlSchoolId, $sahodayaId),
            'receipt_url'          => $this->programReceiptUrl($f->feeReceipt, $schoolId, $urlSchoolId, $sahodayaId),
            'receipt_email_status' => $f->feeReceipt?->receipt_email_status,
            'receipt_emailed_at'   => $f->feeReceipt?->receipt_emailed_at?->toDateTimeString(),
            'receipt_email_error'  => $f->feeReceipt?->receipt_email_error,
            'receipt_email_resend_count' => $f->feeReceipt?->receipt_email_resend_count ?? 0,
            'fee_receipt_id'       => $f->feeReceipt?->id,
            'receipt_status'       => $f->feeReceipt?->status,
            'rejection_reason'     => $f->feeReceipt?->status === 'rejected' ? $f->feeReceipt->rejection_reason : null,
            'receipts_history'     => $this->mapReceiptsHistory($f, $urlSchoolId, $sahodayaId),
        ];
    }

    /** @param  Collection<string, string>  $schoolNames */
    private function mapMcqRow(
        McqRegistration $r,
        Collection $schoolNames,
        ?string $urlSchoolId,
        ?string $sahodayaId,
    ): array {
        $schoolId = $r->school_id;
        $isCancelled = in_array($r->status, ['cancelled', 'withdrawn'], true);
        $label = ($r->exam?->title ?? 'Talent Search').' — '.$r->student?->name;
        if ($isCancelled) {
            $label = 'CANCELLED — '.$label;
        }

        return [
            'id'                   => $r->id,
            'type'                 => 'mcq',
            'school_id'            => $schoolId,
            'school_name'          => $schoolNames->get($schoolId),
            'label'                => $label,
            'level_label'          => null,
            'amount'               => $r->feeReceipt?->amount ?? $r->exam?->fee_amount,
            'status'               => $isCancelled ? 'cancelled' : ($r->feeReceipt?->status === 'approved' ? 'approved' : ($r->feeReceipt?->status ?? 'pending')),
            'is_cancelled'         => $isCancelled,
            'payment_date'         => $r->feeReceipt?->payment_date?->toDateString(),
            'reviewed_at'          => $r->feeReceipt?->reviewed_at?->toDateTimeString(),
            'reviewed_by'          => $r->feeReceipt?->reviewedBy?->name,
            'transaction_ref'      => $r->feeReceipt?->transaction_ref,
            'receipt_number'       => $r->feeReceipt?->receipt_number,
            'proof_url'            => $this->programProofUrl($r->feeReceipt, $schoolId, $urlSchoolId, $sahodayaId),
            'receipt_url'          => $this->programReceiptUrl($r->feeReceipt, $schoolId, $urlSchoolId, $sahodayaId),
            'receipt_email_status' => $r->feeReceipt?->receipt_email_status,
            'receipt_emailed_at'   => $r->feeReceipt?->receipt_emailed_at?->toDateTimeString(),
            'receipt_email_error'  => $r->feeReceipt?->receipt_email_error,
            'receipt_email_resend_count' => $r->feeReceipt?->receipt_email_resend_count ?? 0,
            'fee_receipt_id'       => $r->feeReceipt?->id,
            'receipt_status'       => $r->feeReceipt?->status,
            'rejection_reason'     => $r->feeReceipt?->status === 'rejected' ? $r->feeReceipt->rejection_reason : null,
            'receipts_history'     => $this->mapReceiptsHistory($r, $urlSchoolId, $sahodayaId),
        ];
    }

    private function mapReceiptsHistory(
        $feeable,
        ?string $urlSchoolId,
        ?string $sahodayaId,
        ?FestEvent $event = null,
    ): array {
        $receipts = method_exists($feeable, 'receipts')
            ? $feeable->receipts
            : ($feeable->feeReceipt ? collect([$feeable->feeReceipt]) : collect());

        if ($receipts->isEmpty() && $feeable->feeReceipt) {
            $receipts = collect([$feeable->feeReceipt]);
        }

        $schoolId = $feeable->school_id ?? null;

        $receiptRows = $receipts
            ->map(function (FeeReceipt $r) use ($feeable, $schoolId, $urlSchoolId, $sahodayaId, $event) {
                return [
                    'id'               => 'receipt-'.$r->id,
                    'sort_at'          => $r->created_at,
                    'status'           => $r->status,
                    'amount'           => (float) $r->amount,
                    'transaction_ref'  => $r->transaction_ref,
                    'payment_date'     => $r->payment_date?->toDateString(),
                    'uploaded_at'      => $r->created_at?->toDateTimeString(),
                    'reviewed_at'      => $r->reviewed_at?->toDateTimeString(),
                    'reviewed_by'      => $r->reviewedBy?->name,
                    'rejection_reason' => $r->rejection_reason,
                    'reversal_reason'  => $r->reversal_reason,
                    'reversed_at'      => $r->reversed_at?->toDateTimeString(),
                    'receipt_number'   => $r->receipt_number,
                    'proof_url'        => $this->programProofUrl($r, (string) $schoolId, $urlSchoolId, $sahodayaId, $event, $feeable->id ?? null),
                    'receipt_url'      => $this->programReceiptUrl($r, (string) $schoolId, $urlSchoolId, $sahodayaId, $event),
                ];
            });

        $creditRows = $this->creditsFor($feeable)
            ->map(function ($c) use ($schoolId, $urlSchoolId, $sahodayaId) {
                return [
                    'id'                 => 'credit-'.$c->id,
                    'sort_at'            => $c->created_at,
                    'status'             => 'credit',
                    'amount'             => (float) $c->amount,
                    'uploaded_at'        => $c->created_at?->toDateTimeString(),
                    'credit_reason'      => $c->reason,
                    'applied_at'         => $c->applied_at?->toDateTimeString(),
                    'credit_note_number' => $c->credit_note_number,
                    'credit_note_url'    => $this->creditNoteUrl($c, (string) $schoolId, $urlSchoolId, $sahodayaId),
                ];
            });

        return $receiptRows->concat($creditRows)
            ->sortByDesc('sort_at')
            ->map(fn (array $row) => collect($row)->except('sort_at')->all())
            ->values()
            ->all();
    }

    /**
     * FestFeeCredit/ProgramFeeCredit rows tied to this fee carrier — additive to the
     * receipt list above, not a replacement. Only Fest/MCQ-batch/Training-batch fee
     * records carry credits today (the three aggregate carriers credits are actually
     * created against — see docs/FLOW_GAP_FIX_PLAN.md Phase 1.1/3b.2); individually-billed
     * MCQ/Training registrations and membership payments intentionally return empty here.
     *
     * @return Collection<int, FestFeeCredit|ProgramFeeCredit>
     */
    private function creditsFor($feeable): Collection
    {
        if ($feeable instanceof FestSchoolEventFee) {
            return FestFeeCredit::where('fest_school_event_fee_id', $feeable->id)->get();
        }

        if ($feeable instanceof McqSchoolFee || $feeable instanceof TrainingSchoolFee) {
            return ProgramFeeCredit::where('creditable_type', get_class($feeable))
                ->where('creditable_id', $feeable->id)
                ->get();
        }

        return collect();
    }

    private function creditNoteUrl($credit, string $schoolId, ?string $urlSchoolId, ?string $sahodayaId): ?string
    {
        if (! $credit->credit_note_number) {
            return null;
        }

        $type = $credit instanceof FestFeeCredit ? 'fest' : 'program';

        if ($urlSchoolId) {
            return "/school-admin/{$urlSchoolId}/payments/credit-notes/{$type}/{$credit->id}";
        }

        if ($sahodayaId) {
            return "/sahodaya-admin/{$sahodayaId}/finance/payments/credit-notes/{$type}/{$credit->id}";
        }

        return null;
    }

    private function membershipReceiptUrl(
        MembershipPayment $payment,
        ?string $urlSchoolId,
        ?string $sahodayaId,
    ): ?string {
        if ($payment->status !== 'verified') {
            return null;
        }

        if ($urlSchoolId) {
            return "/school-admin/{$urlSchoolId}/payments/membership/{$payment->id}/receipt";
        }

        if ($sahodayaId) {
            return "/sahodaya-admin/{$sahodayaId}/membership/payments/{$payment->id}/receipt";
        }

        return null;
    }

    private function membershipProofUrl(MembershipPayment $payment, ?string $urlSchoolId, ?string $sahodayaId = null): ?string
    {
        if (! $payment->payment_proof_path) {
            return null;
        }

        if ($urlSchoolId) {
            return "/school-admin/{$urlSchoolId}/registration/payments/{$payment->id}/proof";
        }

        if ($sahodayaId) {
            return "/sahodaya-admin/{$sahodayaId}/membership/payments/{$payment->id}/proof";
        }

        return null;
    }

    private function programProofUrl(
        ?FeeReceipt $receipt,
        string $schoolId,
        ?string $urlSchoolId,
        ?string $sahodayaId,
        ?FestEvent $event = null,
        ?int $schoolFeeId = null,
    ): ?string {
        if (! $receipt || ! $receipt->file_path || $receipt->isSystemCredit()) {
            return null;
        }

        if ($urlSchoolId) {
            return "/school-admin/{$urlSchoolId}/payments/receipts/{$receipt->id}/proof";
        }

        if ($sahodayaId) {
            if ($event && $schoolFeeId) {
                return "/sahodaya-admin/{$sahodayaId}/events/{$event->id}/school-fees/{$schoolFeeId}/proof";
            }

            return "/sahodaya-admin/{$sahodayaId}/finance/payments/receipts/{$receipt->id}/proof";
        }

        return null;
    }

    private function programReceiptUrl(
        ?FeeReceipt $receipt,
        string $schoolId,
        ?string $urlSchoolId,
        ?string $sahodayaId,
        ?FestEvent $event = null,
    ): ?string {
        if (! $receipt || ! in_array($receipt->status, ['approved', 'reversed'], true)) {
            return null;
        }

        if ($urlSchoolId) {
            if ($event && $receipt->status === 'approved') {
                $slug = match ($event->event_type) {
                    'sports'    => 'sports-meet',
                    'kids_fest' => 'kids-fest',
                    default     => 'kalotsav',
                };

                return "/school-admin/{$urlSchoolId}/programs/{$slug}/events/{$event->id}/receipt";
            }

            return "/school-admin/{$urlSchoolId}/payments/receipts/{$receipt->id}";
        }

        if ($sahodayaId) {
            return "/sahodaya-admin/{$sahodayaId}/finance/payments/receipts/{$receipt->id}";
        }

        return null;
    }
}
