<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use App\Support\TenancyDatabase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfileChangeRequest extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'user_id', 'school_id', 'changes_json', 'reason', 'status',
        'school_approval_status', 'school_approved_by', 'school_approved_at',
        'sahodaya_approved_by', 'sahodaya_approved_at', 'resolution_note',
    ];

    protected $casts = [
        'changes_json'         => 'array',
        'school_approved_at'   => 'datetime',
        'sahodaya_approved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
