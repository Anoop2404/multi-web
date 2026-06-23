<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScreenSetting extends Model
{
    protected $fillable = ['tenant_id', 'slug', 'title', 'config_json', 'is_active'];

    protected $casts = [
        'is_active'   => 'boolean',
        'config_json' => 'array',
    ];
}
