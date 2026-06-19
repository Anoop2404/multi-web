<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
    protected $fillable = [
        'school_id', 'academic_year', 'reg_no', 'membership_fee_amount',
        'registration_status', 'school_year_submission_id',
    ];

    protected $casts = ['membership_fee_amount' => 'decimal:2'];

    public function school()     { return $this->belongsTo(Tenant::class, 'school_id'); }
    public function submission() { return $this->belongsTo(SchoolYearSubmission::class, 'school_year_submission_id'); }
    public function payments()   { return $this->hasMany(MembershipPayment::class); }
}
