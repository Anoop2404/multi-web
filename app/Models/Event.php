<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Event extends Model
{
    protected $fillable = ['tenant_id','title','slug','description','image','start_date','end_date','venue','is_upcoming'];
    protected $casts = ['is_upcoming' => 'boolean', 'start_date' => 'date', 'end_date' => 'date'];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function scopeUpcoming($q) { return $q->where('start_date', '>=', now()->toDateString())->orderBy('start_date'); }
    public function scopePast($q) { return $q->where('start_date', '<', now()->toDateString())->orderByDesc('start_date'); }
    public static function boot() {
        parent::boot();
        static::creating(fn($m) => $m->slug = $m->slug ?? Str::slug($m->title));
        static::saved(fn (self $model) => $model->invalidateTenantCache());
        static::deleted(fn (self $model) => $model->invalidateTenantCache());
    }

    public function invalidateTenantCache(): void
    {
        if ($tenant = Tenant::find($this->tenant_id)) {
            $tenant->invalidateCache();
        }
    }
}
