<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingAttendance extends Model
{
    protected $table = 'training_attendance';

    public const STATUSES = ['present', 'absent', 'late', 'with_permission'];

    /** Statuses that count toward certificate / CPD presence. */
    public const PRESENT_LIKE = ['present', 'late'];

    protected $fillable = [
        'session_id', 'registration_id', 'status', 'marked_by', 'marked_at',
        'correction_reason', 'corrected_by', 'approval_status',
    ];

    protected $casts = [
        'marked_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'session_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(TrainingRegistration::class, 'registration_id');
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
