<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobVacancy extends Model
{
    protected $fillable = ['tenant_id','title','description','qualification','experience','last_date','apply_email','is_active'];
    protected $casts = ['is_active' => 'boolean', 'last_date' => 'date'];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function scopeActive($q) { return $q->where('is_active', true)->where(fn($q) => $q->whereNull('last_date')->orWhere('last_date', '>=', now()->toDateString())); }
}
