<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkinPreset extends Model
{
    protected $fillable = ['name','slug','preview_image','description','theme','is_active','display_order'];
    protected $casts = ['theme' => 'array', 'is_active' => 'boolean'];

    public function scopeActive($q) { return $q->where('is_active', true)->orderBy('display_order'); }
}
