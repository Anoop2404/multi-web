<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone working a State event. Deliberately not a user account: most event staff are present for
 * three days and never sign in, so a name and a phone number is the minimum, and user_id is set only
 * for the few who also hold a platform login.
 */
class StateEventStaff extends StateModel
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'state_event_staff';

    /** The jobs a State event actually needs filled, mirroring the module's permission roles. */
    public const ROLES = [
        'event_officer'         => 'Event officer',
        'scrutiny_officer'      => 'Scrutiny officer',
        'stage_manager'         => 'Stage manager',
        'mark_coordinator'      => 'Mark coordinator',
        'attendance_operator'   => 'Attendance operator',
        'certificate_operator'  => 'Certificate operator',
        'report_user'           => 'Report user',
        'volunteer'             => 'Volunteer',
    ];

    protected $fillable = [
        'state_event_id', 'state_id', 'name', 'phone', 'email', 'role',
        'venue_id', 'user_id', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(StateVenue::class, 'venue_id');
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }
}
