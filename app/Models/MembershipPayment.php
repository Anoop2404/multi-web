<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;

class MembershipPayment extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'school_id', 'academic_year', 'registration_id', 'fee_receipt_id', 'amount',
        'payment_proof_path', 'payment_method', 'transaction_ref',
        'uploaded_by_user_id', 'status', 'rejection_reason', 'superseded_by_payment_id',
        'verified_by_user_id', 'verified_at',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'verified_at' => 'datetime',
    ];

    protected $appends = ['proof_url'];

    public function school()       { return $this->belongsToCentralTenant('school_id'); }
    public function registration() { return $this->belongsTo(Registration::class); }
    public function feeReceipt()   { return $this->belongsTo(FeeReceipt::class); }
    public function feeReceipts()  { return $this->morphMany(FeeReceipt::class, 'feeable'); }
    public function supersededBy() { return $this->belongsTo(self::class, 'superseded_by_payment_id'); }

    public function getProofUrlAttribute(): ?string
    {
        if (! $this->payment_proof_path) {
            return null;
        }

        $sahodayaId = $this->school?->parent_id;
        if (! $sahodayaId) {
            return null;
        }

        return route('sahodaya.membership.payments.proof', [
            'tenantId' => $sahodayaId,
            'payment'  => $this->id,
        ]);
    }

    public function getSchoolProofUrlAttribute(): ?string
    {
        if (! $this->payment_proof_path) {
            return null;
        }

        return route('school.registration.payment.proof', [
            'tenantId' => $this->school_id,
            'payment'  => $this->id,
        ]);
    }
}
