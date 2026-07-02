<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestParticipationPolicy extends Model
{
    protected $fillable = [
        'tenant_id', 'scope', 'event_id', 'level_round', 'class_group', 'preset_key',
        'max_onstage_per_school', 'max_offstage_per_school', 'max_group_per_school',
        'max_onstage_per_student', 'max_offstage_per_student', 'max_group_per_student',
        'max_total_per_student', 'one_entry_per_item_per_school',
        'count_submitted_registrations', 'exclude_standbys_from_limits',
        'require_fee_before_approval', 'require_school_qualification', 'is_active',
    ];

    protected $casts = [
        'one_entry_per_item_per_school' => 'boolean',
        'count_submitted_registrations' => 'boolean',
        'exclude_standbys_from_limits' => 'boolean',
        'require_fee_before_approval' => 'boolean',
        'require_school_qualification' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    /** @return array<string, mixed> */
    public function toLimitArray(): array
    {
        return [
            'max_onstage_per_school' => $this->max_onstage_per_school,
            'max_offstage_per_school' => $this->max_offstage_per_school,
            'max_group_per_school' => $this->max_group_per_school,
            'max_onstage_per_student' => $this->max_onstage_per_student,
            'max_offstage_per_student' => $this->max_offstage_per_student,
            'max_group_per_student' => $this->max_group_per_student,
            'max_total_per_student' => $this->max_total_per_student,
            'one_entry_per_item_per_school' => $this->one_entry_per_item_per_school,
            'count_submitted_registrations' => $this->count_submitted_registrations,
            'exclude_standbys_from_limits' => $this->exclude_standbys_from_limits,
            'require_fee_before_approval' => $this->require_fee_before_approval,
        ];
    }
}
