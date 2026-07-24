<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FeeReceiptAttachment;
use App\Models\Tenant;
use App\Services\Fees\ProgramFeeReceiptService;
use App\Support\TenantStorage;

/**
 * Sahodaya-side counterpart to SchoolAdmin\FeeReceiptAttachmentController — one shared
 * endpoint for every program's review pages (Fest, Training, MCQ, Membership) instead of a
 * near-identical action duplicated in FestSchoolEventFeeController, McqPaymentsController,
 * TrainingProgramController, and PaymentVerificationController.
 */
class FeeReceiptAttachmentController extends SahodayaAdminController
{
    public function show(string $tenantId, FeeReceiptAttachment $attachment, ProgramFeeReceiptService $receiptService)
    {
        $attachment->loadMissing('feeReceipt');
        $receipt = $attachment->feeReceipt;
        abort_unless($receipt, 404);

        $schoolId = $receiptService->schoolIdForReceipt($receipt);
        abort_unless($schoolId && Tenant::find($schoolId)?->parent_id === $this->sahodaya->id, 403);

        $disk = config('filesystems.upload_disk', 'shared');
        if (in_array($disk, ['s3', 'private'], true)) {
            return redirect(\Illuminate\Support\Facades\Storage::disk($disk)->temporaryUrl($attachment->file_path, now()->addMinutes(15)));
        }

        return TenantStorage::downloadResponse($this->sahodaya, $attachment->file_path);
    }
}
