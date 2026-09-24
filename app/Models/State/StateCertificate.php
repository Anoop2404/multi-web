<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * One issued certificate. Carries the Sahodaya and the School as printed, and a fingerprint of the
 * result it was printed from — when that result moves, the certificate is stale.
 */
class StateCertificate extends StateModel
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'state_certificates';

    public const TYPES = [
        'merit' => 'Merit (position)',
        'participation' => 'Participation',
        'championship' => 'Championship',
        'judge' => 'Judge',
        'official' => 'Official',
        'volunteer' => 'Volunteer',
        'appreciation' => 'Appreciation',
    ];

    public const GENERATED = 'generated';

    /** The result it was printed from has changed since. */
    public const STALE = 'stale';

    /** Replaced by a regenerated certificate. */
    public const SUPERSEDED = 'superseded';

    protected $fillable = [
        'state_event_id', 'state_id', 'type', 'certificate_number', 'verification_code',
        'registration_id', 'participant_id', 'sahodaya_id', 'sahodaya_name', 'school_name',
        'recipient_name', 'item_id', 'item_code', 'item_name', 'position', 'grade',
        'source_fingerprint', 'status', 'batch_id', 'generated_at', 'printed_at',
    ];

    protected function casts(): array
    {
        return ['generated_at' => 'datetime', 'printed_at' => 'datetime'];
    }

    public function isStale(): bool
    {
        return $this->status === self::STALE;
    }
}
