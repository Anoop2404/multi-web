<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardResultUpload extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'board_result_id',
        'tenant_id',
        'version',
        'file_path',
        'storage_disk',
        'file_name',
        'file_type',
        'uploaded_by',
    ];

    public function boardResult(): BelongsTo
    {
        return $this->belongsTo(BoardResult::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsToCentralTenant();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
