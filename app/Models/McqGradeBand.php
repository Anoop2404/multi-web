<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McqGradeBand extends Model
{
    protected $fillable = [
        'grade_master_id', 'label', 'min_percentage', 'max_percentage',
        'is_pass', 'rank_eligible', 'display_order',
    ];

    protected $casts = [
        'min_percentage' => 'decimal:2',
        'max_percentage' => 'decimal:2',
        'is_pass'        => 'boolean',
        'rank_eligible'  => 'boolean',
    ];

    public function gradeMaster(): BelongsTo
    {
        return $this->belongsTo(McqGradeMaster::class, 'grade_master_id');
    }
}
