<?php

namespace App\Models;

use App\Models\Concerns\ScopesBySahodaya;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
        return Cache::remember(self::cacheKey('labels', $sahodayaId), now()->addHours(6), function () use ($sahodayaId) {
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
        });
    }

    public static function findByCode(string $code, ?string $sahodayaId = null): ?self
    {
        $attributes = Cache::remember(self::cacheKey('by-code:'.$code, $sahodayaId), now()->addHours(6), function () use ($code, $sahodayaId) {
            $stream = static::query()
                ->active()
                ->forSahodaya($sahodayaId)
                ->where('code', $code)
                ->orderByRaw('sahodaya_id is null') // prefer sahodaya-specific override
                ->first();

            return $stream?->getAttributes();
        });

        return $attributes ? (new static)->newFromBuilder($attributes) : null;
    }

    /**
     * Invalidate cached lookups for a stream. Call after create/update/delete so
     * labelsFor()/findByCode() don't serve stale data.
     */
    public static function forgetCache(string $code, ?string $sahodayaId): void
    {
        Cache::forget(self::cacheKey('labels', $sahodayaId));
        Cache::forget(self::cacheKey('by-code:'.$code, $sahodayaId));
    }

    /**
     * exam_streams lives in the per-Sahodaya tenant database, so even the "global"
     * (sahodaya_id null) lookups must be namespaced by the current tenant DB —
     * otherwise a cache entry populated while connected to one tenant's database
     * could leak into another tenant's request.
     */
    private static function cacheKey(string $suffix, ?string $sahodayaId): string
    {
        $scope = $sahodayaId ?? ('db:'.DB::connection()->getDatabaseName());

        return "exam-stream:{$scope}:{$suffix}";
    }
}
