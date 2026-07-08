<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use App\Models\Concerns\TracksPartialPayments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McqSchoolFee extends Model
{
    use BelongsToCentralTenant;
    use TracksPartialPayments;

    protected $fillable = [
        'exam_id', 'school_id', 'student_count', 'total_due', 'amount_paid', 'fee_receipt_id', 'status',
    ];

    protected $casts = [
        'total_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(McqExam::class, 'exam_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsToCentralTenant('school_id');
    }

    public function feeReceipt(): BelongsTo
    {
        return $this->belongsTo(FeeReceipt::class);
    }
}
