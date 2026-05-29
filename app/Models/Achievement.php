<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    protected $fillable = ['tenant_id','title','description','image','category','level','achieved_at','display_order'];
    protected $casts = ['achieved_at' => 'date'];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function scopeByCategory($q, string $category) { return $q->where('category', $category); }
    public function scopeByLevel($q, string $level) { return $q->where('level', $level); }
}
