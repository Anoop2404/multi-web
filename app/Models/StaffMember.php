<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffMember extends Model
{
    protected $fillable = ['tenant_id','name','designation','department','qualification','photo','type','display_order','is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function scopeActive($q) { return $q->where('is_active', true)->orderBy('display_order'); }
    public function scopeByType($q, string $type) { return $q->where('type', $type); }
}
