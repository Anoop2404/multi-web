<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionBankDocument extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'tenant_id', 'master_class_id', 'title', 'subject', 'file_path', 'academic_year', 'download_count',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsToCentralTenant();
    }

    public function masterClass(): BelongsTo
    {
        return $this->belongsTo(MasterClass::class);
    }

    public function scopeForClass($query, int $masterClassId)
    {
        return $query->where('master_class_id', $masterClassId);
    }
}
