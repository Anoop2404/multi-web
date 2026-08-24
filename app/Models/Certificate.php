<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    protected $fillable = [
        'entity_type', 'entity_id', 'cert_type', 'template_id',
        'verification_uuid', 'file_path', 'generated_at', 'email_sent_at',
        'collected_at', 'collected_by_user_id',
        'plain_file_path', 'storage_disk', 'content_hash', 'is_stale',
        'stale_checked_at', 'rendered_at',
    ];

    protected $casts = [
        'generated_at'     => 'datetime',
        'email_sent_at'    => 'datetime',
        'collected_at'     => 'datetime',
        'is_stale'         => 'boolean',
        'stale_checked_at' => 'datetime',
        'rendered_at'      => 'datetime',
    ];

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by_user_id');
    }
}
