<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class UploadedFileBackup extends Model
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'school_id', 'purpose', 'storage_disk', 'storage_path',
        'original_name', 'mime_type', 'size_bytes',
        'related_type', 'related_id', 'uploaded_by_user_id', 'metadata',
        'status', 'total_rows', 'imported_count', 'error_count', 'errors',
    ];

    protected $casts = [
        'metadata' => 'array',
        'errors' => 'array',
    ];

    public function related()
    {
        return $this->morphTo();
    }

    public function existsOnDisk(): bool
    {
        return Storage::disk($this->storage_disk)->exists($this->storage_path);
    }
}
