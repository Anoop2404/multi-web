<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;

class Circular extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = ['tenant_id','title','circular_number','file_path','category','issued_date','academic_year','download_count'];
    protected $casts = ['issued_date' => 'date'];

    public function tenant() { return $this->belongsToCentralTenant(); }
    public function scopeByCategory($q, string $category) { return $q->where('category', $category); }
    public function scopeByYear($q, string $year) { return $q->where('academic_year', $year); }
}
