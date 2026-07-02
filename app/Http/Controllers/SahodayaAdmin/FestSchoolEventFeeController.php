<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestSchoolEventFee;
use App\Models\Tenant;
use App\Models\FestEventInvoice;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestFeeLedgerService;
use App\Services\Events\FestInvoiceService;
use App\Services\Fees\ProgramFeeReceiptMailer;
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

        $receipt = $schoolEventFee->feeReceipt;
        abort_unless($receipt && $receipt->status === 'uploaded', 422, 'No uploaded proof to approve.');

        DB::transaction(function () use ($request, $receipt, $schoolEventFee) {
            $nextNo = app(SahodayaReceiptNumberAllocator::class)->next($this->sahodaya->id);
            $receiptNo = 'EF-'.str_pad((string) $nextNo, 4, '0', STR_PAD_LEFT);

            $receipt->update([
                'status' => 'approved',
                'receipt_number' => $receiptNo,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            $schoolEventFee->update(['status' => 'approved']);

            FestEventInvoice::where('event_id', $schoolEventFee->event_id)
                ->where('school_id', $schoolEventFee->school_id)
                ->update(['status' => 'paid']);
        });

        app(FestFeeLedgerService::class)->postApprovedReceipt($receipt->fresh());

        $schoolEventFee->load(['school', 'feeReceipt', 'event']);
        $festHtml = app(ProgramFeeReceiptService::class)->renderFestSchoolEventFee($schoolEventFee);
        $slug = ProgramRouteMap::slugFromEventType($event->event_type) ?? 'kalotsav';

        app(ProgramFeeReceiptMailer::class)->sendApproved(
            $schoolEventFee->school,
            $schoolEventFee->feeReceipt,
            ProgramRouteMap::labelForSlug($slug).' fee',
            $event->title,
            $festHtml,
            adminPath: "programs/{$slug}/registration",
        );

        $audit->festEvent($event, FestPageActivity::FEES, 'fest.fee.approved', 'School event fee approved', [
            'school_id' => $schoolEventFee->school_id,
        ]);

        return back()->with('success', 'School event fee approved.');
    }

    public function reject(Request $request, string $tenantId, FestEvent $event, FestSchoolEventFee $schoolEventFee, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($schoolEventFee->event_id !== $event->id, 403);

        $data = $request->validate(['rejection_reason' => 'nullable|string|max:500']);

        $receipt = $schoolEventFee->feeReceipt;
        if ($receipt) {
            $receipt->update([
                'status' => 'rejected',
                'rejection_reason' => $data['rejection_reason'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        }

        $schoolEventFee->update([
            'status' => 'rejected',
            'fee_receipt_id' => null,
        ]);

        FestEventInvoice::where('event_id', $schoolEventFee->event_id)
            ->where('school_id', $schoolEventFee->school_id)
            ->where('status', 'paid')
            ->update(['status' => 'issued']);

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
