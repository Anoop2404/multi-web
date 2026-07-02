<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class FestStateProgramItem extends Model
{
    use CentralConnection, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'state_program_id', 'title', 'item_code', 'category', 'stage_type', 'venue_type',
        'competition_format', 'sport_discipline', 'duration_minutes', 'criteria_json',
        'participant_type', 'gender', 'class_group', 'age_group', 'kids_band',
        'max_per_school', 'min_group_size', 'max_group_size', 'qualify_count', 'display_order',
        'fee_amount',
    ];

    protected $casts = [
        'criteria_json' => 'array',
        'fee_amount' => 'decimal:2',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(FestStateProgram::class, 'state_program_id');
    }

    /** @return array<string, mixed> */
    public function toTenantAttributes(): array
    {
        return [
            'title'              => $this->title,
            'item_code'          => $this->item_code,
            'category'           => $this->category,
            'stage_type'         => $this->stage_type,
            'venue_type'         => $this->venue_type,
            'competition_format' => $this->competition_format,
            'sport_discipline'   => $this->sport_discipline,
            'duration_minutes'   => $this->duration_minutes,
            'criteria_json'      => $this->criteria_json,
            'participant_type'   => $this->participant_type,
            'gender'             => $this->gender,
            'class_group'        => $this->class_group,
            'age_group'          => $this->age_group,
            'kids_band'          => $this->kids_band,
            'max_per_school'     => $this->max_per_school,
            'min_group_size'     => $this->min_group_size,
            'max_group_size'     => $this->max_group_size,
            'qualify_count'      => $this->qualify_count,
            'display_order'      => $this->display_order,
            'fee_amount'         => $this->fee_amount,
            'owner_level'        => 'state',
            'state_program_item_id'=> $this->id,
        ];
    }
}
