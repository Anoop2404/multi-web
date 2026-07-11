<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiConfig extends Model
{
    protected $fillable = [
        'sahodaya_id',
        'weight_pass_percent',
        'weight_distinctions',
        'weight_highest_mark',
        'weight_toppers',
        'is_active',
    ];

    protected $casts = [
        'weight_pass_percent' => 'float',
        'weight_distinctions' => 'float',
        'weight_highest_mark' => 'float',
        'weight_toppers' => 'float',
        'is_active' => 'boolean',
    ];

    public static function forSahodaya(string $sahodayaId): self
    {
        return static::query()->firstOrCreate(
            ['sahodaya_id' => $sahodayaId],
            [
                'weight_pass_percent' => 40,
                'weight_distinctions' => 20,
                'weight_highest_mark' => 20,
                'weight_toppers' => 20,
                'is_active' => true,
            ]
        );
    }

    /** @return array{pass_percent: float, distinctions: float, highest_mark: float, toppers: float} */
    public function normalizedWeights(): array
    {
        $raw = [
            'pass_percent' => (float) $this->weight_pass_percent,
            'distinctions' => (float) $this->weight_distinctions,
            'highest_mark' => (float) $this->weight_highest_mark,
            'toppers' => (float) $this->weight_toppers,
        ];
        $sum = array_sum($raw) ?: 1.0;

        return array_map(fn (float $w) => round($w / $sum * 100, 4), $raw);
    }
}
