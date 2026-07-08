<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportJob extends Model
{
    protected $fillable = [
        'user_id', 'export_type', 'filename', 'file_path', 'storage_disk', 'row_count',
        'status', 'error', 'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'row_count'    => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isReady(): bool
    {
        return $this->status === 'completed' && filled($this->file_path);
    }
}
