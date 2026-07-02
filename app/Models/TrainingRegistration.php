<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Certificate;
use App\Models\FeeReceipt;
use App\Models\Tenant;

class TrainingRegistration extends Model
{
    protected $fillable = [
        'program_id', 'teacher_id', 'school_id', 'status', 'fee_receipt_id',
    ];

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
        return $this->belongsTo(Tenant::class, 'school_id');
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
