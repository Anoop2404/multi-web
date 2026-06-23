<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    protected $fillable = [
        'entity_type', 'entity_id', 'template_id',
        'verification_uuid', 'file_path', 'generated_at',
    ];

    protected $casts = ['generated_at' => 'datetime'];
}
