<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StateQualifierIntake extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'state_program_id', 'source_tenant_id', 'source_event_id',
        'idempotency_key', 'status', 'payload', 'payload_hash',
        'reviewed_by', 'reviewed_at', 'review_notes',
    ];

    protected $casts = [
        'payload'     => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(StateQualifierEntry::class, 'intake_id');
    }
}
