<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestMark extends Model
{
    protected $fillable = [
        'event_id', 'item_id', 'participant_id', 'grade', 'position',
        'score', 'measurement_value', 'measurement_unit',
        'ref_data_json', 'locked_by', 'locked_at',
    ];

    protected $casts = [
        'score'         => 'decimal:2',
        'ref_data_json' => 'array',
        'locked_at'     => 'datetime',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(FestParticipant::class, 'participant_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(FestEventItem::class, 'item_id');
    }

    /**
     * Unique key for aggregating school/championship points from FestMark rows.
     * Prevents multi-member team/group/pair entries from duplicating points for the school.
     */
    public function deduplicationKey(): string
    {
        $p = $this->participant;
        $item = $this->item ?? $p?->registration?->item;
        $participantType = strtolower((string) ($item?->participant_type ?? 'individual'));
        $isNonIndividual = $participantType !== 'individual';

        if ($p?->group_id) {
            return 'grp:' . $p->group_id;
        }

        $schoolId = $p?->registration?->school_id ?? $p?->school_id;
        if ($isNonIndividual && $schoolId && $this->item_id) {
            $chest = (string) ($p?->group?->chest_no ?? $p?->chest_no ?? '');
            return 'team:' . $this->item_id . ':' . $schoolId . ($chest !== '' ? (':' . $chest) : '');
        }

        if ($p?->registration_id) {
            return 'reg:' . $p->registration_id;
        }

        return 'mark:' . $this->id;
    }
}
