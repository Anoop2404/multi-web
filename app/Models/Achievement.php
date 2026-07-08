<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = ['tenant_id','title','description','image','category','level','achieved_at','display_order'];
    protected $casts = ['achieved_at' => 'date'];

    public function tenant() { return $this->belongsToCentralTenant(); }
    public function scopeByCategory($q, string $category) { return $q->where('category', $category); }
    public function scopeByLevel($q, string $level) { return $q->where('level', $level); }
}
