<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'school_id', 'academic_year', 'reg_no', 'membership_fee_amount', 'amount_paid', 'fee_override',
        'registration_status', 'school_year_submission_id',
    ];

    protected $casts = [
        'membership_fee_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'fee_override' => 'array',
    ];

    public function school()     { return $this->belongsToCentralTenant('school_id'); }
    public function submission() { return $this->belongsTo(SchoolYearSubmission::class, 'school_year_submission_id'); }
    public function payments()   { return $this->hasMany(MembershipPayment::class); }

    /**
     * Fee still owed for this academic year: current target amount minus whatever has
     * already been verified-paid. Used both for the normal first-time payment (amount_paid
     * starts at 0, so this equals the full fee) and for a post-approval revision where the
     * student count grew into a higher fee slab after an earlier payment was already verified
     * (this then equals just the top-up difference).
     */
    public function outstandingBalance(): float
    {
        if ($this->membership_fee_amount === null) {
            return 0.0;
        }

        return round((float) $this->membership_fee_amount - (float) ($this->amount_paid ?? 0), 2);
    }
}
