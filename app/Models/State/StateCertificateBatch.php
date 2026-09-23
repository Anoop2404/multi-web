<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/** A generation run, so a large print job reports progress rather than appearing to hang. */
class StateCertificateBatch extends StateModel
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'state_certificate_batches';

    protected $fillable = [
        'state_event_id', 'state_id', 'type', 'scope', 'status',
        'requested_count', 'generated_count', 'failed_count', 'error',
        'requested_by_user_id', 'requested_by_name', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}
