<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class PlatformDashboardSnapshot extends Model
{
    use CentralConnection;

    protected $fillable = [
        'total_students', 'total_teachers', 'revenue_this_month_inr',
        'sahodayas_included', 'sahodayas_total', 'computed_at',
    ];

    protected $casts = [
        'revenue_this_month_inr' => 'decimal:2',
        'computed_at' => 'datetime',
    ];
}
