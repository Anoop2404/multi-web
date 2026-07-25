<?php

namespace App\Models;

use App\Models\Concerns\ScopesBySahodaya;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin-configured "out of" (total) marks per Sahodaya, keyed by class (10) or
 * class + stream (12). Schools no longer type this in free-hand — the entry
 * forms resolve and lock this value. See BoardResultMarksConfigService.
 */
class BoardResultMarksConfig extends Model
{
    use ScopesBySahodaya;

    public const DEFAULT_TOTAL_MARKS = 500;

    protected $fillable = [
        'sahodaya_id',
        'class',
        'stream_id',
        'total_marks',
    ];

    protected $casts = [
        'class' => 'integer',
        'total_marks' => 'integer',
    ];

    public function stream(): BelongsTo
    {
        return $this->belongsTo(ExamStream::class, 'stream_id');
    }
}
