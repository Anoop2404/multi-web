<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-school chest number range for a sports FestEvent.
 *
 * @property int         $id
 * @property int         $event_id
 * @property string      $school_id
 * @property int         $chest_no_start   First number reserved for this school.
 * @property int|null    $chest_no_end     Last number (inclusive); NULL = open-ended.
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class FestSchoolChestRange extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'event_id',
        'school_id',
        'chest_no_start',
        'chest_no_end',
    ];

    protected $casts = [
        'chest_no_start' => 'integer',
        'chest_no_end'   => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsToCentralTenant('school_id');
    }
}
