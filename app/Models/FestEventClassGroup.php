<?php

namespace App\Models;

use App\Support\Concerns\HasClassesSuffix;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LEGACY — a Sahodaya-defined class category scoped to a single event, used when that
 * event's `fee_settings.class_group_scheme` was the literal string 'custom'. Superseded by
 * FestClassCategoryScheme (named, Sahodaya-wide, reusable across events) — kept only so any
 * event that already saved 'custom' with rows here keeps resolving correctly. New category
 * setups should go through FestClassCategoryScheme instead. See App\Support\FestClassGroupScheme.
 */
class FestEventClassGroup extends Model
{
    use HasClassesSuffix;

    protected $fillable = ['tenant_id', 'event_id', 'key', 'label', 'description', 'classes', 'sort_order'];

    protected $casts = [
        'classes' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }
}
