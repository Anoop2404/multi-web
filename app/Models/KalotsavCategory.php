<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KalotsavCategory extends Model
{
    protected $fillable = ['kalotsav_event_id','name','group','max_points','display_order'];

    public function event() { return $this->belongsTo(KalotsavEvent::class, 'kalotsav_event_id'); }
    public function results() { return $this->hasMany(KalotsavResult::class); }
}
