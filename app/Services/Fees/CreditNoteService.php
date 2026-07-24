<?php

namespace App\Services\Fees;

use App\Models\FestFeeCredit;
use App\Models\McqSchoolFee;
use App\Models\ProgramFeeCredit;
use App\Models\Tenant;
use App\Models\TrainingSchoolFee;
use App\Support\IndianAmountInWords;
use App\Support\SahodayaReceiptNumberAllocator;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

/**
 * Generates a numbered, storable, downloadable credit-note document for a FestFeeCredit or
 * ProgramFeeCredit — the "compensating document" gap flagged in
 * docs/CROSS_SYSTEM_FLOW_GAP_AUDIT.md §6b and specced in docs/FLOW_GAP_FIX_PLAN.md Phase
 * 3b.2. Mirrors ProgramFeeReceiptService's numbering/storage pattern (same
 * SahodayaReceiptNumberAllocator counter, same "generate once, read many times" shape) but
 * for the credit side of the ledger instead of the payment side.
 *
 * Deliberately does NOT touch the ledger — FestFeeLedgerService::postCreditIssued() already
 * posts the accounting entry at each fest credit-creation site; this service only produces
 * the human-readable document a school can see/download, and is safe to fail without any
 * financial consequence (every call site wraps this in try/catch, same convention as
 * notifications elsewhere in this codebase — a document-generation failure must never block
 * the underlying credit from being recorded).
 */
class CreditNoteService
{
    public function issue(FestFeeCredit|ProgramFeeCredit $credit): FestFeeCredit|ProgramFeeCredit
    {
        if ($credit->credit_note_number && $credit->generated_note_path) {
            return $credit;
        }

        [$sahodaya, $school, $contextLabel] = $credit instanceof FestFeeCredit
            ? $this->festContext($credit)
            : $this->programContext($credit);

        if (! $sahodaya || ! $school) {
            return $credit;
        }

        return DB::transaction(function () use ($credit, $sahodaya, $school, $contextLabel) {
            $noteNo = $credit->credit_note_number ?: $this->formatNumber(
                app(SahodayaReceiptNumberAllocator::class)->next($sahodaya->id),
            );

            $html = View::make('receipts.credit-note-official', [
                'noteNo'       => $noteNo,
                'sahodaya'     => $sahodaya,
                'school'       => $school,
                'contextLabel' => $contextLabel,
                'reason'       => $credit->reason,
                'issuedBy'     => $credit->createdBy?->name ?? 'Sahodaya Admin',
                'issuedAt'     => $credit->created_at ?? now(),
                'appliedAt'    => $credit->applied_at,
                'amount'       => (float) $credit->amount,
                'amountWords'  => IndianAmountInWords::rupees((float) $credit->amount),
            ])->render();

            $path = $credit->generated_note_path ?: $this->storeHtml($sahodaya, $noteNo, $html);

            $credit->update([
                'credit_note_number'  => $noteNo,
                'generated_note_path' => $path,
            ]);

            return $credit->fresh();
        });
    }

    /**
     * Mirrors ProgramFeeReceiptService::schoolIdForReceipt() — used by both
     * PaymentHistoryController (school) and UnifiedPaymentsController (Sahodaya) to check
     * a requester actually owns/administers the school this credit belongs to before
     * serving the document.
     */
    public function schoolIdForCredit(FestFeeCredit|ProgramFeeCredit $credit): ?string
    {
        if ($credit instanceof FestFeeCredit) {
            return $credit->schoolEventFee?->school_id;
        }

        $credit->loadMissing('creditable');
        $creditable = $credit->creditable;

        if ($creditable instanceof McqSchoolFee || $creditable instanceof TrainingSchoolFee) {
            return $creditable->school_id;
        }

        // Membership settlement credits (SchoolMembershipCancellationService::
        // cancelWithSettlement()) use creditable_type = Tenant::class directly — the school
        // itself is the creditable, not a per-program fee row.
        if ($creditable instanceof Tenant) {
            return $creditable->id;
        }

        return null;
    }

    public function readOrGenerate(FestFeeCredit|ProgramFeeCredit $credit): ?string
    {
        $existing = $this->readGenerated($credit);
        if ($existing) {
            return $existing;
        }

        $issued = $this->issue($credit);

        return $this->readGenerated($issued);
    }

    private function readGenerated(FestFeeCredit|ProgramFeeCredit $credit): ?string
    {
        $path = $credit->generated_note_path;
        if (! $path) {
            return null;
        }

        $disk = TenantStorage::uploadDisk();
        if (! Storage::disk($disk)->exists($path)) {
            return null;
        }

        return Storage::disk($disk)->get($path);
    }

    /** @return array{0: ?Tenant, 1: ?Tenant, 2: string} */
    private function festContext(FestFeeCredit $credit): array
    {
        $credit->loadMissing('schoolEventFee.event', 'schoolEventFee.school');
        $fee = $credit->schoolEventFee;
        $school = $fee?->school;
        $sahodaya = $school?->parent_id ? Tenant::find($school->parent_id) : null;

        return [$sahodaya, $school, $fee?->event?->title ?? 'Fest event fee'];
    }

    /** @return array{0: ?Tenant, 1: ?Tenant, 2: string} */
    private function programContext(ProgramFeeCredit $credit): array
    {
        $credit->loadMissing('creditable');
        $creditable = $credit->creditable;

        if ($creditable instanceof McqSchoolFee) {
            $creditable->loadMissing('exam', 'school');

            return [
                $creditable->school?->parent_id ? Tenant::find($creditable->school->parent_id) : null,
                $creditable->school,
                $creditable->exam?->title ?? 'Talent Search exam fee',
            ];
        }

        if ($creditable instanceof TrainingSchoolFee) {
            $creditable->loadMissing('program', 'school');

            return [
                $creditable->school?->parent_id ? Tenant::find($creditable->school->parent_id) : null,
                $creditable->school,
                $creditable->program?->title ?? 'Training programme fee',
            ];
        }

        return [null, null, 'Programme fee'];
    }

    private function formatNumber(int $sequence): string
    {
        return 'CN-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function storeHtml(Tenant $sahodaya, string $noteNo, string $html): string
    {
        $relative = 'sahodaya/'.$sahodaya->id.'/credit-notes/note-'.$noteNo.'.html';
        $disk = TenantStorage::uploadDisk();
        Storage::disk($disk)->put($relative, $html);

        return $relative;
    }
}
