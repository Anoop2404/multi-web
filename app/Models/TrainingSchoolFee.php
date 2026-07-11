<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use App\Models\Concerns\TracksPartialPayments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TrainingSchoolFee extends Model
{
    use BelongsToCentralTenant;
    use TracksPartialPayments;

    protected $fillable = [
        'program_id', 'school_id', 'teacher_count', 'total_due', 'amount_paid', 'fee_receipt_id', 'status',
    ];

    protected $casts = [
        'total_due'   => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'program_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsToCentralTenant('school_id');
    }

    public function feeReceipt(): BelongsTo
    {
        return $this->belongsTo(FeeReceipt::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(TrainingInvoice::class, 'school_fee_id');
    }
}
