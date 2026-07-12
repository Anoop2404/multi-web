<?php

namespace App\Models;

use App\Models\Concerns\ScopesBySahodaya;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamStream extends Model
{
    use ScopesBySahodaya;
    protected $fillable = [
        'sahodaya_id',
        'code',
        'label',
        'examination_type',
        'sort_order',
        'is_active',
        'default_subjects',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'default_subjects' => 'array',
    ];

    public function toppers(): HasMany
    {
        return $this->hasMany(Topper::class, 'stream_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeForSahodaya(Builder $q, ?string $sahodayaId): Builder
    {
        return $q->where(function (Builder $inner) use ($sahodayaId) {
            $inner->whereNull('sahodaya_id');
            if ($sahodayaId) {
                $inner->orWhere('sahodaya_id', $sahodayaId);
            }
        });
    }

    /** @return array<string, string> code => label */
    public static function labelsFor(?string $sahodayaId = null): array
    {
        // Prefer Sahodaya-specific overrides over global defaults (same rule as findByCode).
        return static::query()
            ->active()
            ->forSahodaya($sahodayaId)
            ->orderByRaw('sahodaya_id is null')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->unique('code')
            ->mapWithKeys(fn (self $s) => [$s->code => $s->label])
            ->all();
    }

    public static function findByCode(string $code, ?string $sahodayaId = null): ?self
    {
        return static::query()
            ->active()
            ->forSahodaya($sahodayaId)
            ->where('code', $code)
            ->orderByRaw('sahodaya_id is null') // prefer sahodaya-specific override
            ->first();
    }
}
