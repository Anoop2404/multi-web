<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteSectionVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'site_section_id', 'variant', 'config', 'note', 'created_by', 'created_at',
    ];

    protected $casts = [
        'config' => 'array',
        'created_at' => 'datetime',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(SiteSection::class, 'site_section_id');
    }
}
