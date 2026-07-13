<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;

class OfficeBearers extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = ['tenant_id','name','role','school_name','photo','phone','email','term_from','term_to','display_order','is_active'];
    protected $casts = ['is_active' => 'boolean'];

    protected $appends = ['photo_url'];

    public function tenant() { return $this->belongsToCentralTenant(); }
    public function scopeActive($q) { return $q->where('is_active', true)->orderBy('display_order'); }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photoUrl();
    }

    public function photoUrl(): ?string
    {
        if (! filled($this->photo) || $this->photo === '0') {
            return null;
        }

        if (str_starts_with($this->photo, 'http://') || str_starts_with($this->photo, 'https://')) {
            return $this->photo;
        }

        $version = $this->updated_at?->timestamp ?? 0;

        return route('tenant.office-bearers.photo', ['bearer' => $this->id], absolute: false)
            .($version ? '?v='.$version : '');
    }
}
