<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopperSubjectMark extends Model
{
    protected $fillable = [
        'topper_id',
        'subject_id',
        'subject_label',
        'marks',
    ];

    protected $casts = [
        'marks' => 'float',
    ];

    public function topper(): BelongsTo
    {
        return $this->belongsTo(Topper::class);
    }
}
