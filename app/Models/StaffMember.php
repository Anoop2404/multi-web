<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;

class StaffMember extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = ['tenant_id','name','designation','department','qualification','photo','type','display_order','is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function tenant() { return $this->belongsToCentralTenant(); }
    public function scopeActive($q) { return $q->where('is_active', true)->orderBy('display_order'); }
    public function scopeByType($q, string $type) { return $q->where('type', $type); }
}
