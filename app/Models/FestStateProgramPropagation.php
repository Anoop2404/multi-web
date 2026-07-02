<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class FestStateProgramPropagation extends Model
{
    use CentralConnection;

    protected $fillable = [
        'state_program_id', 'sahodaya_id', 'tenant_event_id', 'level_round',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(FestStateProgram::class, 'state_program_id');
    }

    public function sahodaya(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'sahodaya_id');
    }
}
