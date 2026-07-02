<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $fillable = ["tenant_id", "slug", "title", "body_template", "channels_json", "is_active"];

    protected $casts = [
        'is_active' => 'boolean',
        'channels_json' => 'array',
    ];
}
