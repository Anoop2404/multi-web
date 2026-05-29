<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoardResult extends Model
{
    protected $fillable = ['tenant_id','class','academic_year','total_appeared','pass_count','pass_percent','distinctions','first_class','subject_stats'];
    protected $casts = ['subject_stats' => 'array', 'pass_percent' => 'float'];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function toppers() { return $this->hasMany(Topper::class)->orderBy('rank'); }
    public function scopeForClass($q, int $class) { return $q->where('class', $class); }
    public function scopeLatestFirst($q) { return $q->orderByDesc('academic_year'); }
}
