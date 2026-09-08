<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use App\Support\TenantStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class QuestionBankDocument extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'tenant_id', 'master_class_id', 'title', 'subject', 'file_path', 'file_size', 'academic_year', 'download_count',
    ];

    protected $appends = ['file_size_label'];

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

    /** Human-readable size (e.g. "2.4 MB"), or null when unknown. */
    public function getFileSizeLabelAttribute(): ?string
    {
        $bytes = (int) $this->file_size;
        if ($bytes <= 0) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return round($value, $power === 0 ? 0 : 1).' '.$units[$power];
    }

    /**
     * Documents uploaded before the file_size column existed have it as null —
     * compute it once from the actual file on disk and persist it, so it never
     * needs a separate backfill migration/command.
     */
    public function backfillFileSizeIfMissing(): void
    {
        if ($this->file_size !== null) {
            return;
        }

        $disk = TenantStorage::findLocalDisk($this->file_path) ?? TenantStorage::uploadDisk();

        try {
            if (Storage::disk($disk)->exists($this->file_path)) {
                $size = Storage::disk($disk)->size($this->file_path);
                $this->forceFill(['file_size' => $size])->saveQuietly();
            }
        } catch (\Throwable) {
            // Best-effort only — an unreadable disk shouldn't break the listing page,
            // it just won't show a size for this document this time.
        }
    }
}
