<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use App\Support\AchievementCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Achievement extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'image',
        'category',
        'level',
        'academic_year',
        'source_award_id',
        'is_system_generated',
        'achieved_at',
        'display_order',
    ];

    protected $casts = [
        'achieved_at' => 'date',
        'is_system_generated' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsToCentralTenant();
    }

    public function sourceAward(): BelongsTo
    {
        return $this->belongsTo(AcademicAward::class, 'source_award_id');
    }

    public function scopeByCategory($q, string $category)
    {
        $key = AchievementCatalog::normalizeCategory($category);

        return $q->where('category', $key ?? $category);
    }

    public function scopeByLevel($q, string $level)
    {
        $key = AchievementCatalog::normalizeLevel($level);

        return $q->where('level', $key ?? $level);
    }

    public function scopeByAcademicYear($q, string $year)
    {
        return $q->where('academic_year', $year);
    }
}
