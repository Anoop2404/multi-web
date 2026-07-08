<?php

namespace App\Services\Membership;

use App\Models\MembershipPayment;
use App\Models\UploadedFileBackup;
use App\Support\TenantStorage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MembershipPaymentProofService
{
    public function download(MembershipPayment $payment): BinaryFileResponse|StreamedResponse|Response
    {
        $school = $payment->school;
        if (! $school) {
            return $this->missingResponse('School record not found for this payment.');
        }

        foreach ($this->proofPaths($payment) as $path) {
            try {
                return TenantStorage::downloadResponse($school, $path);
            } catch (NotFoundHttpException) {
                continue;
            }
        }

        foreach ($this->backupCandidates($payment) as $backup) {
            try {
                return TenantStorage::downloadPrivate(
                    $backup->storage_path,
                    $backup->storage_disk,
                );
            } catch (NotFoundHttpException) {
                continue;
            }
        }

        return $this->missingResponse('Payment proof file is missing from storage. Ask the school to re-upload the proof.');
    }

    /** @return list<string> */
    private function proofPaths(MembershipPayment $payment): array
    {
        $payment->loadMissing('feeReceipt');

        return collect([
            $payment->payment_proof_path,
            $payment->feeReceipt?->file_path,
        ])
            ->filter()
            ->map(fn ($path) => ltrim((string) $path, '/'))
            ->unique()
            ->values()
            ->all();
    }

    private function backupCandidates(MembershipPayment $payment)
    {
        $related = UploadedFileBackup::query()
            ->where('related_type', $payment->getMorphClass())
            ->where('related_id', $payment->id)
            ->get();

        $nearUpload = UploadedFileBackup::query()
            ->where('school_id', $payment->school_id)
            ->where('purpose', 'payment_proof')
            ->when($payment->created_at, function ($query) use ($payment) {
                $query->whereBetween('created_at', [
                    $payment->created_at->copy()->subMinutes(10),
                    $payment->created_at->copy()->addMinutes(10),
                ]);
            })
            ->get();

        return $related
            ->concat($nearUpload)
            ->unique('id')
            ->sortByDesc('created_at')
            ->values();
    }

    private function missingResponse(string $message): Response
    {
        if (request()->boolean('preview')) {
            return response(
                '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f8fafc;color:#334155;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px}.box{max-width:560px;background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:28px;text-align:center;box-shadow:0 20px 45px rgba(15,23,42,.08)}h1{font-size:18px;margin:0 0 8px;color:#0f172a}p{font-size:14px;line-height:1.6;margin:0;color:#64748b}</style></head><body><div class="box"><h1>Payment proof unavailable</h1><p>'.e($message).'</p></div></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8'],
            );
        }

        abort(404, $message);
    }
}
