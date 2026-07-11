<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TrainingCategory extends Model
{
    protected $fillable = [
        'tenant_id', 'code', 'label', 'is_active', 'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    /** @return list<array{code: string, label: string}> */
    public static function defaultDefinitions(): array
    {
        return [
            ['code' => 'induction', 'label' => 'Induction / Orientation'],
            ['code' => 'subject_enrichment', 'label' => 'Subject Enrichment'],
            ['code' => 'pedagogy', 'label' => 'Pedagogy & Methodology'],
            ['code' => 'leadership', 'label' => 'Leadership & Management'],
            ['code' => 'assessment', 'label' => 'Assessment & Evaluation'],
            ['code' => 'ict', 'label' => 'ICT & Digital'],
        ];
    }

    /** Seed default categories for a tenant when none exist yet. */
    public static function ensureDefaults(string $tenantId): void
    {
        if (static::forTenant($tenantId)->exists()) {
            return;
        }

        foreach (static::defaultDefinitions() as $i => $def) {
            static::create([
                'tenant_id' => $tenantId,
                'code' => $def['code'],
                'label' => $def['label'],
                'is_active' => true,
                'display_order' => $i + 1,
            ]);
        }
    }

    public static function makeUniqueCode(string $tenantId, string $label, ?string $code = null, ?int $ignoreId = null): string
    {
        $base = Str::slug($code ?: $label, '_');
        if ($base === '') {
            $base = 'category';
        }
        $base = Str::limit($base, 60, '');

        $candidate = $base;
        $n = 2;
        while (
            static::forTenant($tenantId)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('code', $candidate)
                ->exists()
        ) {
            $candidate = Str::limit($base, 56, '').'_'.$n;
            $n++;
        }

        return $candidate;
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function programs(): HasMany
    {
        return $this->hasMany(TrainingProgram::class, 'category_id');
    }
}
