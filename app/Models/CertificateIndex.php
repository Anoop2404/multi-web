<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * verification_uuid -> tenant_id lookup, always central-connection. Lets the public
 * certificate routes (verify/print/pdf — unauthenticated, keyed only by a uuid with no
 * tenant identifier anywhere in the request) find the right tenant database in one query
 * instead of scanning every Sahodaya's database on every hit. Populated by
 * CertificateObserver/McqCertificateObserver on creation, and self-healed by
 * PublicCertificateController on a scan-fallback hit for any certificate that predates
 * this table.
 */
class CertificateIndex extends Model
{
    use CentralConnection;

    protected $table = 'certificate_index';

    public const SOURCE_CERTIFICATE = 'certificates';

    public const SOURCE_MCQ = 'mcq_certificates';

    protected $fillable = [
        'verification_uuid', 'tenant_id', 'source_table',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public static function recordFor(string $uuid, string $tenantId, string $source): void
    {
        static::updateOrCreate(
            ['verification_uuid' => $uuid],
            ['tenant_id' => $tenantId, 'source_table' => $source],
        );
    }
}
