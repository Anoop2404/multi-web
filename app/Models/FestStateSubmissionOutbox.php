<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FestStateSubmissionOutbox extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'fest_state_submission_outbox';

    protected $fillable = [
        'state_program_id', 'source_event_id', 'submission_type', 'idempotency_key',
        'payload', 'payload_hash', 'status', 'attempts', 'last_error',
        'state_response_id', 'state_response', 'submitted_by', 'sent_at', 'completed_at',
    ];

    protected $casts = [
        'payload'         => 'array',
        'state_response'  => 'array',
        'sent_at'         => 'datetime',
        'completed_at'    => 'datetime',
    ];
}
