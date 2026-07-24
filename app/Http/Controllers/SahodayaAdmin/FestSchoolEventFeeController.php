<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestFeeCredit;
use App\Models\FestSchoolEventFee;
use App\Models\Tenant;
use App\Models\FestEventInvoice;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestFeeLedgerService;
use App\Services\Events\FestInvoiceService;
use App\Services\Fees\OfflineProgramFeeOrchestrator;
use App\Services\Fees\ProgramFeeReceiptService;
use App\Support\FestPageActivity;
use App\Support\ProgramRouteMap;
use App\Support\SahodayaReceiptNumberAllocator;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FestSchoolEventFeeController extends SahodayaAdminController
{
    public function approve(Request $request, string $tenantId, FestEvent $event, FestSchoolEventFee $schoolEventFee, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($schoolEventFee->event_id !== $event->id, 403);

        $receipt = $schoolEventFee->receipts()->where('status', 'uploaded')->latest('id')->first()
            ?? $schoolEventFee->feeReceipt;
        abort_unless($receipt && $receipt->status === 'uploaded', 422, 'No uploaded proof to approve.');

        $fullyPaid = DB::transaction(function () use ($request, $receipt, $schoolEventFee, $event) {
            // Lock this fee record for the duration of the approval + overpayment-
            // reconciliation check below, consistent with the locking added to reject/
            // cancel (see docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §13.4) — otherwise a
            // concurrent reject/cancel racing against this approval could change total_due
            // between the read above and the overpayment check below.
            FestSchoolEventFee::whereKey($schoolEventFee->id)->lockForUpdate()->first();

            $nextNo = app(SahodayaReceiptNumberAllocator::class)->next($this->sahodaya->id);
            $receiptNo = 'EF-'.str_pad((string) $nextNo, 4, '0', STR_PAD_LEFT);

            $receipt->update([
                'status' => 'approved',
                'receipt_number' => $receiptNo,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            // Accumulate into amount_paid; status becomes partial or approved.
            $schoolEventFee->refresh();
            $schoolEventFee->refreshPaidState();
            $fresh = $schoolEventFee->fresh();
            $fullyPaid = $fresh->isFullyPaid();

            // Reconcile an overpayment caused by total_due shrinking *after* this receipt
            // was uploaded but *before* it got reviewed — e.g. a registration this receipt
            // was meant to help cover got cancelled/rejected in between. Without this, the
            // excess just sits as an untracked, invisible overpayment: amount_paid can
            // legitimately exceed total_due here (refreshPaidState() doesn't clamp it), but
            // nothing before this surfaced or recorded that as money owed back to the
            // school. Scoped to head_id = null records, matching every other FestFeeCredit
            // site. See docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §4.
            if ($fresh->head_id === null) {
                $overpaid = round((float) $fresh->amount_paid - (float) $fresh->total_due, 2);
                if ($overpaid > 0) {
                    $credit = FestFeeCredit::create([
                        'fest_school_event_fee_id' => $fresh->id,
                        'amount' => $overpaid,
                        'reason' => 'Overpayment reconciled at approval — balance decreased after this proof was uploaded',
                        'created_by_user_id' => $request->user()->id,
                    ]);
                    app(FestFeeLedgerService::class)->postCreditIssued($credit);

                    try {
                        app(\App\Services\Fees\CreditNoteService::class)->issue($credit);
                    } catch (\Throwable) {
                        // credit is already recorded + posted; the note can be regenerated later
                    }
                }
            }

            if ($fullyPaid) {
                // Fest no longer needs a separate registration approval — settling the fee
                // auto-approves the school's registrations. For per-head billing this record
                // covers only its own head, so only that head's registrations are approved,
                // not the whole event (head_id is null for non-head events/fee models, which
                // keeps the original whole-event behavior there).
                app(\App\Services\Events\FestRegistrationApprovalService::class)
                    ->approveSchoolEvent($event, $schoolEventFee->school_id, $schoolEventFee->head_id);

                if ($schoolEventFee->head_id === null) {
                    FestEventInvoice::where('event_id', $schoolEventFee->event_id)
                        ->where('school_id', $schoolEventFee->school_id)
                        ->update(['status' => 'paid']);
                }
            }

            return $fullyPaid;
        });

        app(FestFeeLedgerService::class)->postApprovedReceipt($receipt->fresh());

        $schoolEventFee->load(['school', 'feeReceipt', 'event']);
        $festHtml = app(ProgramFeeReceiptService::class)->renderFestSchoolEventFee($schoolEventFee);
        $slug = ProgramRouteMap::slugFromEventType($event->event_type) ?? 'kalotsav';

        app(OfflineProgramFeeOrchestrator::class)->notifyApproved(
            $schoolEventFee->school,
            $schoolEventFee->feeReceipt,
            ProgramRouteMap::labelForSlug($slug).' fee',
            $event->title,
            $festHtml,
            adminPath: "programs/{$slug}/registration",
        );

        $audit->festEvent($event, FestPageActivity::FEES, 'fest.fee.approved', 'School event fee approved', [
            'school_id' => $schoolEventFee->school_id,
            'fully_paid' => $fullyPaid,
        ]);

        $balance = $schoolEventFee->fresh()->outstandingBalance();

        return back()->with('success', $fullyPaid
            ? 'School event fee fully paid — registrations approved.'
            : 'Partial payment of ₹'.number_format((float) $receipt->amount, 2).' approved. Balance ₹'.number_format($balance, 2).' pending.');
    }

    public function reject(Request $request, string $tenantId, FestEvent $event, FestSchoolEventFee $schoolEventFee, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($schoolEventFee->event_id !== $event->id, 403);

        $data = $request->validate(['rejection_reason' => 'nullable|string|max:500']);

        $receipt = $schoolEventFee->receipts()->where('status', 'uploaded')->latest('id')->first()
            ?? $schoolEventFee->feeReceipt;
        if ($receipt && $receipt->status === 'uploaded') {
            $receipt->update([
                'status' => 'rejected',
                'rejection_reason' => $data['rejection_reason'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        }

        // Preserve any already-approved partial payments; fall back to partial/pending.
        $schoolEventFee->refresh();
        $schoolEventFee->refreshPaidState();
        if ($schoolEventFee->fresh()->outstandingBalance() > 0 && ! $schoolEventFee->fresh()->isPartiallyPaid()) {
            $schoolEventFee->update(['status' => 'rejected']);
        }

        // Invoice-status rollup for per-head fee records is handled by FestInvoiceService
        // (issueForSchool sums every head's fee record); only reset it directly here for
        // the old non-head, single-record path.
        if ($schoolEventFee->head_id === null && ! $schoolEventFee->fresh()->isFullyPaid()) {
            FestEventInvoice::where('event_id', $schoolEventFee->event_id)
                ->where('school_id', $schoolEventFee->school_id)
                ->where('status', 'paid')
                ->update(['status' => 'issued']);
        }

        $audit->festEvent($event, FestPageActivity::FEES, 'fest.fee.rejected', 'School event fee rejected', [
            'school_id' => $schoolEventFee->school_id,
        ]);

        $slug = ProgramRouteMap::slugFromEventType($event->event_type) ?? 'kalotsav';
        app(OfflineProgramFeeOrchestrator::class)->notifyRejected(
            $schoolEventFee->school,
            ProgramRouteMap::labelForSlug($slug).' fee',
            $event->title,
            $data['rejection_reason'] ?? null,
            adminPath: "programs/{$slug}/registration",
        );

        return back()->with('success', 'Fee rejected. School can re-upload.');
    }

    public function proof(string $tenantId, FestEvent $event, FestSchoolEventFee $schoolEventFee)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($schoolEventFee->event_id !== $event->id, 403);

        $receipt = $schoolEventFee->receipts()->where('status', 'uploaded')->latest('id')->first()
            ?? $schoolEventFee->feeReceipt
            ?? $schoolEventFee->receipts()->latest('id')->first();

        abort_if(!$receipt || $receipt->isSystemCredit(), 404, 'This balance was settled from fee credit — there is no uploaded payment proof to view.');

        $path = $receipt->file_path;
        abort_unless($path, 404);

        $disk = config('filesystems.upload_disk', 'shared');
        if (in_array($disk, ['s3', 'private'], true)) {
            return redirect(\Illuminate\Support\Facades\Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(15)));
        }

        return TenantStorage::downloadResponse($this->sahodaya, $path);
    }

    public function receiptProof(string $tenantId, FestEvent $event, FestSchoolEventFee $schoolEventFee, \App\Models\FeeReceipt $feeReceipt)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($schoolEventFee->event_id !== $event->id, 403);
        abort_if($feeReceipt->feeable_type !== FestSchoolEventFee::class || (int) $feeReceipt->feeable_id !== (int) $schoolEventFee->id, 403);
        abort_if($feeReceipt->isSystemCredit(), 404, 'This balance was settled from fee credit — there is no uploaded payment proof to view.');

        $path = $feeReceipt->file_path;
        abort_unless($path, 404);

        $disk = config('filesystems.upload_disk', 'shared');
        if (in_array($disk, ['s3', 'private'], true)) {
            return redirect(\Illuminate\Support\Facades\Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(15)));
        }

        return TenantStorage::downloadResponse($this->sahodaya, $path);
    }

    public function recalculate(Request $request, string $tenantId, FestEvent $event, FestSchoolEventFee $schoolEventFee)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($schoolEventFee->event_id !== $event->id, 403);

        app(\App\Services\Events\FestSchoolEventFeeService::class)->recalculate($event, $schoolEventFee->school_id);

        return back()->with('success', 'Fees successfully recalculated for this school.');
    }

    /**
     * Manual override for a "partial" record whose approved-receipt total
     * genuinely covers what the school was billed at approval time, but
     * `total_due` moved afterward (a registration changed between approval
     * and the next page-load recalculation). There is no direct "force
     * approved" status flip — instead this waives the residual mismatch by
     * bringing `total_due` down to `amount_paid`, then re-derives status the
     * normal way, so the record freezes exactly like any other fully-paid
     * record (future recalculate() calls leave it alone).
     */
    public function forceApprove(Request $request, string $tenantId, FestEvent $event, FestSchoolEventFee $schoolEventFee, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($schoolEventFee->event_id !== $event->id, 403);

        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        $schoolEventFee->refresh();
        $due = (float) $schoolEventFee->total_due;

        $receipt = $schoolEventFee->feeReceipt
            ?? $schoolEventFee->receipts()->latest('id')->first();

        DB::transaction(function () use ($schoolEventFee, $receipt, $due, $event, $request) {
            if ($receipt) {
                $nextNo = $receipt->receipt_number ?: ('EF-'.str_pad((string) app(SahodayaReceiptNumberAllocator::class)->next($this->sahodaya->id), 4, '0', STR_PAD_LEFT));
                $receipt->update([
                    'status' => 'approved',
                    'amount' => $due,
                    'receipt_number' => $nextNo,
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                ]);
                $schoolEventFee->update(['fee_receipt_id' => $receipt->id]);
            }

            $schoolEventFee->refreshPaidState();

            // This is the one place a FestFeeCredit actually gets consumed — see
            // FestSchoolEventFeeService::markCreditsApplied() and
            // docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §13 for why it's wired here rather
            // than into recalculate()/attachPayment() directly.
            app(\App\Services\Events\FestSchoolEventFeeService::class)
                ->markCreditsApplied($schoolEventFee, $due);

            app(\App\Services\Events\FestRegistrationApprovalService::class)
                ->approveSchoolEvent($event, $schoolEventFee->school_id, $schoolEventFee->head_id);

            if ($schoolEventFee->head_id === null) {
                FestEventInvoice::where('event_id', $schoolEventFee->event_id)
                    ->where('school_id', $schoolEventFee->school_id)
                    ->update(['status' => 'paid']);
            }
        });

        if ($receipt) {
            app(FestFeeLedgerService::class)->postApprovedReceipt($receipt->fresh());
        }

        $audit->festEvent($event, FestPageActivity::FEES, 'fest.fee.force_approved', 'School event fee manually approved in full', [
            'school_id' => $schoolEventFee->school_id,
            'total_due' => $due,
            'reason'    => $data['reason'] ?? 'Manual admin approval',
        ]);

        return back()->with('success', 'School event fee approved in full (₹'.number_format($due, 2).') — registrations approved.');
    }
}
