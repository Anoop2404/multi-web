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

        // No s3/private temporaryUrl() redirect branch here (unlike some sibling
        // proof-serving actions) — that redirect was producing a broken/blank response for
        // this endpoint. downloadResponse() streams the file directly and is what every
        // other working proof/attachment endpoint already uses (SchoolAdmin's own version of
        // this controller, UnifiedPaymentsController::proof(),
        // PaymentHistoryController::programProof()) — kept consistent with those.
        return TenantStorage::downloadResponse($this->sahodaya, $attachment->file_path);
    }
}
