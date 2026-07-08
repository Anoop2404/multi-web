<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;

class TcRequest extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = ['tenant_id','student_name','class','division','dob','admission_number','academic_year','parent_name','phone','email','reason','status','admin_notes','issued_date'];
    protected $casts = ['dob' => 'date', 'issued_date' => 'date'];

    public function tenant() { return $this->belongsToCentralTenant(); }
    public function scopePending($q) { return $q->where('status', 'pending'); }
}
