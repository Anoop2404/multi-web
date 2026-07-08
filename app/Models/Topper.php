<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;

class Topper extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = ['board_result_id','tenant_id','name','photo','percentage','total_marks','marks_obtained','subject_marks','is_perfect_scorer','stream','rank'];
    protected $casts = ['subject_marks' => 'array', 'is_perfect_scorer' => 'boolean', 'percentage' => 'float'];

    public function boardResult() { return $this->belongsTo(BoardResult::class); }
    public function tenant() { return $this->belongsToCentralTenant(); }
}
