<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingSchoolFee extends Model
{
    protected $fillable = [
        'program_id', 'school_id', 'teacher_count', 'total_due', 'fee_receipt_id', 'status',
    ];

    protected $casts = [
        'total_due' => 'decimal:2',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'program_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'school_id');
    }

    public function feeReceipt(): BelongsTo
    {
        return $this->belongsTo(FeeReceipt::class);
    }
}
