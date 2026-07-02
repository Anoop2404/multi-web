<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolUserEventScope extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'school_id', 'user_id', 'program_slug', 'scope_type', 'event_id', 'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
