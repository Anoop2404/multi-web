<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsArticle extends Model
{
    protected $fillable = ['tenant_id','title','slug','body','image','category','is_featured','published_at'];
    protected $casts = ['is_featured' => 'boolean', 'published_at' => 'datetime'];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function scopePublished($q) { return $q->whereNotNull('published_at')->where('published_at','<=',now()); }
    public function scopeFeatured($q) { return $q->where('is_featured', true); }
    public static function boot() {
        parent::boot();
        static::creating(fn($m) => $m->slug = $m->slug ?? Str::slug($m->title));
    }
}
