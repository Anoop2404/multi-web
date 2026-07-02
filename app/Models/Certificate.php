<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    protected $fillable = [
        'entity_type', 'entity_id', 'cert_type', 'template_id',
        'verification_uuid', 'file_path', 'generated_at',
        'collected_at', 'collected_by_user_id',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'collected_at' => 'datetime',
    ];

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by_user_id');
    }
}
