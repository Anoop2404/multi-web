<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class FestStateProgram extends Model
{
    use CentralConnection, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'title', 'event_type', 'conduct_levels', 'academic_year',
        'registration_open', 'registration_close', 'event_start', 'event_end',
        'venue', 'fee_type', 'fee_amount', 'level_fees', 'level_policies', 'status', 'description', 'created_by_user_id',
        'state_domain_id', 'state_flow_mode', 'qualifier_policy',
    ];

    protected $casts = [
        'conduct_levels'    => 'array',
        'level_fees'        => 'array',
        'level_policies'    => 'array',
        'qualifier_policy'  => 'array',
        'registration_open' => 'date',
        'registration_close'=> 'date',
        'event_start'       => 'date',
        'event_end'         => 'date',
        'fee_amount'        => 'decimal:2',
    ];

    public function propagations(): HasMany
    {
        return $this->hasMany(FestStateProgramPropagation::class, 'state_program_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FestStateProgramItem::class, 'state_program_id')->orderBy('display_order');
    }

    public function stateDomain(): BelongsTo
    {
        return $this->belongsTo(StateDomain::class, 'state_domain_id');
    }

    public function conductsAt(string $level): bool
    {
        return in_array($level, $this->conduct_levels ?? [], true);
    }

    /** @return array<string, string> */
    public static function levelLabels(): array
    {
        return [
            'state'    => 'State',
            'sahodaya' => 'Sahodaya (cluster)',
            'school'   => 'School',
        ];
    }
}
