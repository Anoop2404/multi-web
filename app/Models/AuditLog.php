<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class AuditLog extends Model
{
    use CentralConnection;

    protected $fillable = [
        'user_id', 'category', 'action', 'description',
        'subject_type', 'subject_id', 'ip_address', 'properties',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
