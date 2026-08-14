<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteSiteVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'website_site_id', 'action', 'template_key', 'template_version',
        'snapshot_json', 'created_by', 'created_at',
    ];

    protected $casts = [
        'snapshot_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(WebsiteSite::class, 'website_site_id');
    }
}
