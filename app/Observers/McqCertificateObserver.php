<?php

namespace App\Observers;

use App\Models\CertificateIndex;
use App\Models\McqCertificate;
use App\Support\TenancyDatabase;
use Illuminate\Support\Facades\Log;

/** Mcq counterpart to CertificateObserver — see its docblock. */
class McqCertificateObserver
{
    public function created(McqCertificate $certificate): void
    {
        $tenant = TenancyDatabase::currentTenant();
        if (! $tenant) {
            return;
        }

        try {
            CertificateIndex::recordFor($certificate->verification_uuid, $tenant->id, CertificateIndex::SOURCE_MCQ);
        } catch (\Throwable $e) {
            Log::warning('Could not index MCQ certificate for public lookup', [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
