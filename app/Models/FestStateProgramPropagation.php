<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class FestStateProgramPropagation extends Model
{
    use BelongsToCentralTenant;

    use CentralConnection;

    protected $fillable = [
        'state_program_id', 'sahodaya_id', 'tenant_event_id', 'level_round',
        'program_updated_at_when_synced',
    ];

    protected $casts = [
        'program_updated_at_when_synced' => 'datetime',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(FestStateProgram::class, 'state_program_id');
    }

    public function sahodaya(): BelongsTo
    {
        return $this->belongsToCentralTenant('sahodaya_id');
    }

    /**
     * True when State has edited this program since this Sahodaya's event was created.
     * Informational only — see STATE_SAHODAYA_RULE_BOUNDARY_FIX_PLAN_2026_08_13.md Set 1
     * item 3. Does NOT mean the Sahodaya's event is "out of date" or needs fixing; the
     * Sahodaya's own settings/policy for their round are intentionally never auto-updated
     * (Set 1 items 1-2) — this just tells both sides that State's template has moved on.
     */
    public function isDivergedFromState(): bool
    {
        $program = $this->relationLoaded('program') ? $this->program : $this->program()->first();

        if (! $program || ! $this->program_updated_at_when_synced) {
            return false;
        }

        return $program->updated_at?->gt($this->program_updated_at_when_synced) ?? false;
    }
}
