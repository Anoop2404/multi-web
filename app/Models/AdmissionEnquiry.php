<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;

class AdmissionEnquiry extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = ['tenant_id','student_name','dob','class_applying','parent_name','phone','email','address','message','status','admin_notes','academic_year'];
    protected $casts = ['dob' => 'date'];

    public function tenant() { return $this->belongsToCentralTenant(); }
    public function scopeNew($q) { return $q->where('status', 'new'); }
}
