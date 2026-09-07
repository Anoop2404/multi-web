<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestAppeal extends Model
{
    /** Disputing an already-recorded result — the original, still-default behavior. */
    public const TYPE_DISPUTE = 'dispute';

    /** Bypasses School-level selection: grants a slot in the Sahodaya-level item. */
    public const TYPE_SAHODAYA_WILDCARD = 'sahodaya_wildcard';

    /** Bypasses Sahodaya-level qualification: grants a slot in the State-level item. */
    public const TYPE_STATE_WILDCARD = 'state_wildcard';

    public const WILDCARD_TYPES = [self::TYPE_SAHODAYA_WILDCARD, self::TYPE_STATE_WILDCARD];

    protected $fillable = [
        'event_id', 'appeal_type', 'participant_id', 'student_id', 'item_id',
        'reason', 'fee_amount', 'fee_paid_at', 'status',
        'submitted_by_user_id', 'resolved_by_user_id',
        'resolution_note', 'resolved_at', 'granted_registration_id', 'granted_state_reference',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'fee_paid_at' => 'datetime',
        'fee_amount'  => 'decimal:2',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(FestParticipant::class, 'participant_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(FestEventItem::class, 'item_id');
    }

    public function grantedRegistration(): BelongsTo
    {
        return $this->belongsTo(FestRegistration::class, 'granted_registration_id');
    }

    public function isWildcard(): bool
    {
        return in_array($this->appeal_type, self::WILDCARD_TYPES, true);
    }
}
