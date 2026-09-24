<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StateQualifierIntake extends StateModel
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'state_program_id', 'state_id', 'source_tenant_id', 'sahodaya_id', 'sahodaya_name', 'source_event_id',
        'idempotency_key', 'status', 'payload', 'payload_hash',
        'reviewed_by', 'reviewed_at', 'review_notes',
    ];

    protected $casts = [
        'payload'     => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function sahodaya(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(StateSahodaya::class, 'sahodaya_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(StateQualifierEntry::class, 'intake_id');
    }
}
