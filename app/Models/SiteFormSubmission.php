<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteFormSubmission extends Model
{
    protected $fillable = [
        'site_form_id', 'payload_json', 'ip', 'user_agent', 'is_spam',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'is_spam' => 'boolean',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(SiteForm::class, 'site_form_id');
    }
}
