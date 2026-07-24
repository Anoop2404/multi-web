<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FeeReceiptAttachment;
use App\Services\Fees\ProgramFeeReceiptService;
use Illuminate\Support\Facades\Storage;

/**
 * Single shared endpoint for viewing any extra proof image attached to a FeeReceipt,
 * reused across every payment flow (Fest, Training, MCQ, Membership) instead of adding a
 * near-identical action to each program's own controller. Ownership is resolved generically
 * via ProgramFeeReceiptService::schoolIdForReceipt(), which already knows how to find the
 * owning school for all four feeable types. Mirrors PaymentHistoryController::
 * programProof()'s existing local-disk-response pattern for consistency.
 */
class FeeReceiptAttachmentController extends SchoolAdminController
{
    public function show(string $tenantId, FeeReceiptAttachment $attachment, ProgramFeeReceiptService $receiptService)
    {
        $attachment->loadMissing('feeReceipt');
        $receipt = $attachment->feeReceipt;
        abort_unless($receipt, 404);

        abort_if($receiptService->schoolIdForReceipt($receipt) !== $this->school->id, 403);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($attachment->file_path), 404, 'Attachment file not found.');

        return response()->file($disk->path($attachment->file_path));
    }
}
