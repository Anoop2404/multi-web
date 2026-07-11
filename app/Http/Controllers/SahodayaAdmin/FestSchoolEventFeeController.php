<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
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
            $fullyPaid = $schoolEventFee->fresh()->isFullyPaid();

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

        return back()->with('success', 'Fee rejected. School can re-upload.');
    }

    public function proof(string $tenantId, FestEvent $event, FestSchoolEventFee $schoolEventFee)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($schoolEventFee->event_id !== $event->id, 403);

        $path = $schoolEventFee->feeReceipt?->file_path;
        abort_unless($path, 404);

        $disk = config('filesystems.upload_disk', 'shared');
        if (in_array($disk, ['s3', 'private'], true)) {
            return redirect(\Illuminate\Support\Facades\Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(15)));
        }

        return TenantStorage::downloadResponse($this->sahodaya, $path);
    }
}
