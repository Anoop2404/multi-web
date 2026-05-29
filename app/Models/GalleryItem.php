<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GalleryItem extends Model
{
    protected $fillable = ['album_id','tenant_id','image_path','caption','display_order'];

    public function album() { return $this->belongsTo(GalleryAlbum::class); }
    public function tenant() { return $this->belongsTo(Tenant::class); }
}
