<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KalotsavResult extends Model
{
    protected $fillable = ['kalotsav_event_id','kalotsav_category_id','school_tenant_id','school_name','position','points','grade','notes'];

    public function event() { return $this->belongsTo(KalotsavEvent::class, 'kalotsav_event_id'); }
    public function category() { return $this->belongsTo(KalotsavCategory::class, 'kalotsav_category_id'); }
    public function school() { return $this->belongsTo(Tenant::class, 'school_tenant_id'); }
}
