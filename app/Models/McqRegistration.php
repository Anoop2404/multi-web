<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class McqRegistration extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'exam_id', 'student_id', 'teacher_id', 'school_id',
        'hall_ticket_no', 'hall_room', 'seat_no',
        'status', 'approval_status', 'approved_at', 'approved_by_user_id',
        'attendance_status', 'attendance_marked_at', 'attendance_marked_by',
        'fee_receipt_id',
        'started_at', 'submitted_at', 'draft_answers',
        'cancelled_at', 'cancelled_by_user_id',
    ];

    protected $casts = [
        'started_at'            => 'datetime',
        'submitted_at'          => 'datetime',
        'draft_answers'         => 'array',
        'approved_at'           => 'datetime',
        'attendance_marked_at'  => 'datetime',
        'cancelled_at'          => 'datetime',
    ];

    /** @param  \Illuminate\Database\Eloquent\Builder<McqRegistration>  $query */
    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'cancelled');
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * A registration can be cancelled by the school only while it is still pending:
     * not approved, no hall ticket issued, and the student has not started the exam.
     */
    public function canBeCancelledBySchool(): bool
    {
        return $this->status === 'registered'
            && $this->approval_status !== 'approved'
            && empty($this->hall_ticket_no);
    }

    public function approvalStatusLabel(): string
    {
        return match ($this->approval_status) {
            'pending_payment'  => 'Pending payment',
            'pending_approval' => 'Pending Sahodaya approval',
            'approved'         => 'Approved',
            'rejected'         => 'Rejected',
            default            => ucfirst(str_replace('_', ' ', (string) $this->approval_status)),
        };
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(McqExam::class, 'exam_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsToCentralTenant('school_id');
    }

    public function mark(): HasOne
    {
        return $this->hasOne(McqMark::class, 'registration_id');
    }

    public function feeReceipt(): BelongsTo
    {
        return $this->belongsTo(FeeReceipt::class, 'fee_receipt_id');
    }
}
