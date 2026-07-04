<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FestTaxonomyMaster extends Model
{
    protected $fillable = [
        'tenant_id', 'dimension', 'entry_key', 'label', 'sort_order', 'is_active', 'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'meta'      => 'array',
    ];

    public const DIMENSIONS = [
        'sport_discipline'    => 'Sport discipline',
        'venue_type'          => 'Venue type',
        'competition_format'  => 'Competition format',
        'participant_type'    => 'Participant type',
        'stage_type'          => 'Stage type',
        'arts_category'       => 'Arts category',
        'gender'              => 'Gender category',
    ];
}
