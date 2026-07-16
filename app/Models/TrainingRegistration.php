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
        'program_id', 'teacher_id', 'school_id', 'status', 'waitlist_position', 'fee_status', 'amount_paid', 'fee_receipt_id',
        'registration_source', 'consent_at', 'department', 'teacher_created', 'pending_school_id',
    ];

    protected $casts = [
        'amount_paid'     => 'decimal:2',
        'consent_at'      => 'datetime',
        'teacher_created' => 'boolean',
    ];

    protected $appends = [
        'display_school_name',
    ];

    protected static function booted(): void
    {
        // Safety net: every registration must resolve to either a real school or a
        // pending-school request awaiting Sahodaya approval. All current creation
        // paths (QR public form, portal self-registration, school CSV import)
        // already enforce this at the controller/service layer — this guard just
        // stops a future code path from silently creating an orphaned row with
        // neither, which is otherwise invisible until someone notices "—" in the
        // registrations list with no "Pending school" tag.
        static::creating(function (self $registration) {
            if (! $registration->school_id && ! $registration->pending_school_id) {
                throw new \RuntimeException(
                    'TrainingRegistration must have either school_id or pending_school_id set.'
                );
            }
        });
    }

    public function getDisplaySchoolNameAttribute(): string
    {
        return $this->displaySchoolName();
    }

    /** Training fee is defined on the program, not on the registration row. */
    public function feeTotalDue(): float
    {
        $this->loadMissing('program');
        $program = $this->program;
        if (! $program || $program->usesSchoolBatchFee()) {
            // School batch fee is billed on TrainingSchoolFee, not per teacher.
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

    public function pendingSchool(): BelongsTo
    {
        return $this->belongsTo(TrainingPendingSchool::class, 'pending_school_id');
    }

    /**
     * School label for UI/exports. Pending-school QR rows must not show the Sahodaya
     * name (historically stored as a holding school_id placeholder).
     */
    public function displaySchoolName(): string
    {
        $this->loadMissing(['pendingSchool', 'school']);

        if (filled($this->pendingSchool?->school_name)) {
            return (string) $this->pendingSchool->school_name;
        }

        if ($this->school instanceof Tenant && $this->school->type === 'school') {
            return (string) $this->school->name;
        }

        return '—';
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(TrainingFeedback::class, 'registration_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(TrainingInvoice::class, 'registration_id');
    }
}
