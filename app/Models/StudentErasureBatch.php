<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A point-in-time snapshot taken right before a Super Admin permanently
 * erases a school's students, so the action can be reverted afterwards.
 * Lives in the school's parent Sahodaya database (like Student itself).
 */
class StudentErasureBatch extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'school_id', 'school_name', 'student_count', 'snapshot',
        'erased_by_user_id', 'erased_by_name', 'erased_by_email', 'erased_at',
        'restored_at', 'restored_by_user_id', 'restored_by_name',
    ];

    protected $casts = [
        'snapshot'    => 'array',
        'erased_at'   => 'datetime',
        'restored_at' => 'datetime',
    ];

    public function school()
    {
        return $this->belongsToCentralTenant('school_id');
    }

    public function isRestored(): bool
    {
        return $this->restored_at !== null;
    }
}
