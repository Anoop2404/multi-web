<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingInvoice extends Model
{
    use BelongsToCentralTenant;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_PAID = 'paid';

    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'program_id',
        'school_id',
        'registration_id',
        'school_fee_id',
        'invoice_number',
        'amount',
        'status',
        'issued_at',
        'pdf_path',
    ];

    protected $casts = [
        'amount'    => 'decimal:2',
        'issued_at' => 'datetime',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'program_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsToCentralTenant('school_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(TrainingRegistration::class, 'registration_id');
    }

    public function schoolFee(): BelongsTo
    {
        return $this->belongsTo(TrainingSchoolFee::class, 'school_fee_id');
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
