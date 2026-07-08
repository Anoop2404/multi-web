<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;

class OfficeBearers extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = ['tenant_id','name','role','school_name','photo','phone','email','term_from','term_to','display_order','is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function tenant() { return $this->belongsToCentralTenant(); }
    public function scopeActive($q) { return $q->where('is_active', true)->orderBy('display_order'); }
}
