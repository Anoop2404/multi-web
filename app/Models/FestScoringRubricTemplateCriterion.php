<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scoring column (e.g. "Content", "Presentation") within a named
 * FestScoringRubricTemplate — same shape as FestMarkCriterion, just keyed by template_id
 * instead of item_id so the same named rubric can be reused across many items/events.
 */
class FestScoringRubricTemplateCriterion extends Model
{
    protected $fillable = ['tenant_id', 'template_id', 'label', 'max_score', 'sort_order'];

    protected $casts = [
        'max_score' => 'decimal:2',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(FestScoringRubricTemplate::class, 'template_id');
    }
}
