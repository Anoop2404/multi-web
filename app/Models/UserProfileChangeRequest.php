<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfileChangeRequest extends Model
{
    protected $fillable = [
        'user_id', 'school_id', 'changes_json', 'reason', 'status',
        'school_approval_status', 'school_approved_by', 'school_approved_at',
        'sahodaya_approved_by', 'sahodaya_approved_at', 'resolution_note',
    ];

    protected $casts = [
        'changes_json'         => 'array',
        'school_approved_at'   => 'datetime',
        'sahodaya_approved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'school_id');
    }
}
