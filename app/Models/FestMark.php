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

        // group_id merging only makes sense for an actual team/group/pair/trio item —
        // an individual item's winners must never collapse into one row just because a
        // stray group_id (e.g. left over from an unrelated batch/family registration
        // flow) happens to be set on their participant row, or two different students
        // from the same school silently vanish into a single result.
        if ($isNonIndividual && $p?->group_id) {
            return 'grp:' . $p->group_id;
        }

        $schoolId = $p?->registration?->school_id ?? $p?->school_id;
        if ($isNonIndividual && $schoolId && $this->item_id) {
            $chest = (string) ($p?->group?->chest_no ?? $p?->chest_no ?? '');
            return 'team:' . $this->item_id . ':' . $schoolId . ($chest !== '' ? (':' . $chest) : '');
        }

        // NOT keyed by registration_id here: an individual item with max_per_school > 1
        // lets one school register several distinct students under a single
        // FestRegistration row (FestRegistrationCreateService::createForSchool()), each
        // with their own FestParticipant and FestMark. Keying by registration_id
        // collapsed every one of those students but the first (by position/score order)
        // into a single row — a same-school pair or trio in an individual item silently
        // lost all but their top scorer everywhere this key drives deduping (school
        // points, the public item-results page, etc). A participant is the correct
        // atomic unit for an individual entrant; registration is not.
        if ($p?->id) {
            return 'participant:' . $p->id;
        }

        return 'mark:' . $this->id;
    }
}
