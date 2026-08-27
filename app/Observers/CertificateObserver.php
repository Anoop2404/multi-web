<?php

namespace App\Observers;

use App\Models\Certificate;
use App\Models\CertificateIndex;
use App\Support\TenancyDatabase;
use Illuminate\Support\Facades\Log;

/**
 * Keeps CertificateIndex (verification_uuid -> tenant_id) in sync so the public
 * verify/print/pdf routes can find the right tenant database from the uuid alone,
 * without scanning every Sahodaya on every request. Best-effort: a failure here must
 * never break certificate issuance — PublicCertificateController's scan-fallback still
 * finds and self-heals any certificate this observer missed.
 */
class CertificateObserver
{
    public function created(Certificate $certificate): void
    {
        $tenant = TenancyDatabase::currentTenant();
        if (! $tenant) {
            return;
        }

        try {
            CertificateIndex::recordFor($certificate->verification_uuid, $tenant->id, CertificateIndex::SOURCE_CERTIFICATE);
        } catch (\Throwable $e) {
            Log::warning('Could not index certificate for public lookup', [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
