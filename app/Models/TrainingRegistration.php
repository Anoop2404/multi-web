<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use App\Models\Concerns\TracksPartialPayments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Certificate;
use App\Models\FeeReceipt;
use App\Models\Tenant;

class TrainingRegistration extends Model
{
    use BelongsToCentralTenant;
    use TracksPartialPayments;

    protected $fillable = [
        'program_id', 'teacher_id', 'school_id', 'status', 'fee_status', 'amount_paid', 'fee_receipt_id',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
    ];

    /** Training fee is defined on the program, not on the registration row. */
    public function feeTotalDue(): float
    {
        $this->loadMissing('program');
        $program = $this->program;
        if (! $program) {
            return 0.0;
        }

        $amount = (float) ($program->fee_amount ?? 0);
        if ($amount > 0 && $program->registration_close) {
            $amount = app(\App\Services\Ledger\LateFeeCalculator::class)->apply(
                $amount,
                $program->registration_close->toDateString(),
                $program->late_fee_amount ? (float) $program->late_fee_amount : null,
                $program->penalty_amount ? (float) $program->penalty_amount : null,
            );
        }

        return round($amount, 2);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'program_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsToCentralTenant('school_id');
    }

    public function feeReceipt(): BelongsTo
    {
        return $this->belongsTo(FeeReceipt::class, 'fee_receipt_id');
    }

    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class, 'entity_id')
            ->where('entity_type', self::class);
    }
}
