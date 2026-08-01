<?php

namespace App\Models;

use App\Models\Concerns\ScopesBySahodaya;
use Illuminate\Database\Eloquent\Model;

/**
 * Sahodaya-wide toggles for the Toppers hub reports. One row per sahodaya.
 *
 * - use_common_ranking: ignore per-stream/per-subject TopperCountConfig overrides and
 *   resolve Top-N/tie-mode/rank-style from the single "overall" scope config everywhere.
 * - no_rank: skip rank numbering on reports entirely; just order by percentage descending.
 */
class TopperRankingSetting extends Model
{
    use ScopesBySahodaya;

    protected $fillable = [
        'sahodaya_id',
        'use_common_ranking',
        'no_rank',
    ];

    protected $casts = [
        'use_common_ranking' => 'boolean',
        'no_rank' => 'boolean',
    ];

    public static function forSahodaya(string $sahodayaId): self
    {
        return static::query()->firstOrCreate(
            ['sahodaya_id' => $sahodayaId],
            [
                'use_common_ranking' => false,
                'no_rank' => false,
            ]
        );
    }
}
