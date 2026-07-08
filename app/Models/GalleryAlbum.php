<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GalleryAlbum extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = ['tenant_id','title','slug','description','cover_image','display_order'];

    public function tenant() { return $this->belongsToCentralTenant(); }
    public function items() { return $this->hasMany(GalleryItem::class, 'album_id')->orderBy('display_order'); }
    public static function boot() {
        parent::boot();
        static::creating(fn($m) => $m->slug = $m->slug ?? Str::slug($m->title));
    }
}
