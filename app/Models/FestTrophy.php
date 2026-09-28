<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestTrophy extends Model
{
    protected $table = 'fest_trophies';

    public const TYPE_OVERALL = 'overall';
    public const TYPE_CATEGORY = 'category';
    public const TYPE_ITEM = 'item';
    public const TYPE_ITEM_GROUP = 'item_group';
    public const TYPE_INDIVIDUAL_CHAMPIONSHIP = 'individual_championship';

    public const AWARD_SCHOOL = 'school';
    public const AWARD_INDIVIDUAL = 'individual';

    protected $fillable = [
        'event_id',
        'template_id',
        'trophy_no',
        'title',
        'trophy_type',
        'position',
        'award_type',
        'category_key',
        'item_id',
        'item_name_pattern',
        'item_ids',
        'item_group_name',
        'gender',
        'notes',
        'is_rolling',
        'donor_name',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'trophy_no' => 'integer',
        'position' => 'integer',
        'sort_order' => 'integer',
        'is_rolling' => 'boolean',
        'is_active' => 'boolean',
        'item_ids' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FestTrophyTemplate::class, 'template_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(FestEventItem::class, 'item_id');
    }

    public function positionOrdinal(): string
    {
        return match ($this->position) {
            1 => '1st',
            2 => '2nd',
            3 => '3rd',
            4 => '4th',
            5 => '5th',
            default => "{$this->position}th",
        };
    }
}
