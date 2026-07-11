<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardResultRanking extends Model
{
    protected $fillable = [
        'sahodaya_id',
        'academic_year',
        'examination_type',
        'class',
        'scope',
        'entity_type',
        'entity_id',
        'board_result_id',
        'rank',
        'score',
        'tie_rule_applied',
        'meta',
    ];

    protected $casts = [
        'score' => 'float',
        'meta' => 'array',
    ];

    public function boardResult(): BelongsTo
    {
        return $this->belongsTo(BoardResult::class);
    }
}
