<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McqSchoolFee extends Model
{
    protected $fillable = [
        'exam_id', 'school_id', 'student_count', 'total_due', 'fee_receipt_id', 'status',
    ];

    protected $casts = [
        'total_due' => 'decimal:2',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(McqExam::class, 'exam_id');
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
