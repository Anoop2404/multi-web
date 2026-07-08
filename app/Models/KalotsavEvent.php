<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;

class KalotsavEvent extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = ['tenant_id','name','type','academic_year','event_date','venue','description','is_active','results_published'];
    protected $casts = ['is_active' => 'boolean', 'results_published' => 'boolean', 'event_date' => 'date'];

    public function tenant() { return $this->belongsToCentralTenant(); }
    public function categories() { return $this->hasMany(KalotsavCategory::class)->orderBy('display_order'); }
    public function results() { return $this->hasMany(KalotsavResult::class); }

    public function scoreboardBySchool()
    {
        return $this->results()
            ->selectRaw('school_tenant_id, school_name, SUM(points) as total_points')
            ->groupBy('school_tenant_id', 'school_name')
            ->orderByDesc('total_points')
            ->get();
    }
}
