<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'school_id', 'academic_year', 'reg_no', 'membership_fee_amount', 'fee_override',
        'registration_status', 'school_year_submission_id',
    ];

    protected $casts = [
        'membership_fee_amount' => 'decimal:2',
        'fee_override' => 'array',
    ];

    public function school()     { return $this->belongsToCentralTenant('school_id'); }
    public function submission() { return $this->belongsTo(SchoolYearSubmission::class, 'school_year_submission_id'); }
    public function payments()   { return $this->hasMany(MembershipPayment::class); }
}
