<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class McqRegistration extends Model
{
    protected $fillable = [
        'exam_id', 'student_id', 'teacher_id', 'school_id',
        'hall_ticket_no', 'hall_room', 'seat_no',
        'status', 'approval_status', 'approved_at', 'approved_by_user_id',
        'attendance_status', 'attendance_marked_at', 'attendance_marked_by',
        'fee_receipt_id',
        'started_at', 'submitted_at', 'draft_answers',
    ];

    protected $casts = [
        'started_at'            => 'datetime',
        'submitted_at'          => 'datetime',
        'draft_answers'         => 'array',
        'approved_at'           => 'datetime',
        'attendance_marked_at'  => 'datetime',
    ];

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
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
        return $this->belongsTo(Tenant::class, 'school_id');
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
