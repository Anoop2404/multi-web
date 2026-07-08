<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolDocument extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'school_id', 'document_type_id', 'file_path', 'storage_disk', 'file_name',
        'valid_from', 'valid_to', 'status', 'rejection_reason',
        'uploaded_by_user_id', 'reviewed_by_user_id', 'reviewed_at',
    ];

    protected $casts = [
        'valid_from'  => 'date',
        'valid_to'    => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsToCentralTenant('school_id');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(SchoolDocumentType::class, 'document_type_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isExpired(): bool
    {
        return $this->valid_to && $this->valid_to->isPast();
    }
}
