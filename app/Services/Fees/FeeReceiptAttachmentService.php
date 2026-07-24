<?php

namespace App\Services\Fees;

use App\Models\FeeReceipt;
use App\Models\FeeReceiptAttachment;
use App\Support\TenantStorage;
use Illuminate\Http\UploadedFile;

/**
 * Stores extra proof images for a FeeReceipt beyond its primary file_path — the shared
 * primitive behind the multi-image payment proof upload feature (Fest/Training/MCQ/
 * Membership). One payment = one FeeReceipt = one review decision; this only adds more
 * evidence photos to that same receipt, it never creates additional receipts.
 *
 * Usage: each upload flow validates `payment_proof` as an array (min 1, max 5), stores
 * $files[0] as the FeeReceipt's `file_path` exactly as it already did before this feature
 * (zero change to that part), then calls attachExtra() with the remaining files.
 */
class FeeReceiptAttachmentService
{
    public const MAX_FILES = 5;

    /**
     * @param  list<UploadedFile>  $files  Every file EXCEPT the one already stored as the
     *     receipt's primary file_path (pass array_slice($files, 1), not the full array).
     */
    public function attachExtra(FeeReceipt $receipt, array $files, string $directory): void
    {
        foreach (array_values($files) as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = TenantStorage::storeUploadedFile($file, $directory);

            FeeReceiptAttachment::create([
                'fee_receipt_id'     => $receipt->id,
                'file_path'          => $path,
                'original_filename'  => $file->getClientOriginalName(),
                // sort_order 0 is implicitly the primary file_path image; extras start at 1
                // so a combined, ordered gallery (primary + attachments) is a stable sort.
                'sort_order'         => $index + 1,
            ]);
        }
    }
}
