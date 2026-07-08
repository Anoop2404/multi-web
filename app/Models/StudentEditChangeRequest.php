<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use App\Support\TenancyDatabase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEditChangeRequest extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'school_id', 'student_id', 'change_type', 'status', 'changes_json', 'photo_path',
        'reason', 'resolution_note', 'requested_by_user_id', 'reviewed_by_user_id', 'reviewed_at',
        'school_approval_status', 'school_approved_by', 'school_approved_at', 'school_rejection_note',
        'submitted_by_role', 'escalation_type',
    ];

    protected $casts = [
        'changes_json'       => 'array',
        'reviewed_at'        => 'datetime',
        'school_approved_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsToCentralTenant('school_id');
    }

    /** @param  Builder<self>  $query */
    public function scopeForSahodaya(Builder $query, string $sahodayaId): Builder
    {
        $schoolIds = TenancyDatabase::schoolIdsFor($sahodayaId);

        if ($schoolIds === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn('school_id', $schoolIds);
    }
}
