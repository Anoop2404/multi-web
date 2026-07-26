<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Sahodaya-defined class category scoped to one event, used when that event's
 * `fee_settings.class_group_scheme` is 'custom' instead of one of the fixed
 * cbse/sahodaya/cluster schemes. See App\Support\FestClassGroupScheme.
 */
class FestEventClassGroup extends Model
{
    protected $fillable = ['tenant_id', 'event_id', 'key', 'label', 'description', 'sort_order'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }
}
