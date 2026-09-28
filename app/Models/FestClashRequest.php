<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestClashRequest extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'event_id', 'school_id', 'participant_id', 'schedule_id_a', 'schedule_id_b', 'schedule_ids',
        'description', 'requested_resolution', 'status', 'resolution_note',
        'requested_by_user_id', 'reviewed_by_user_id', 'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'schedule_ids' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsToCentralTenant('school_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(FestParticipant::class, 'participant_id');
    }

    public function scheduleA(): BelongsTo
    {
        return $this->belongsTo(FestSchedule::class, 'schedule_id_a');
    }

    public function scheduleB(): BelongsTo
    {
        return $this->belongsTo(FestSchedule::class, 'schedule_id_b');
    }

    /**
     * Every schedule slot this report flagged as clashing (two or more) — schedule_ids
     * when set (a report made with the multi-slot picker), falling back to
     * schedule_id_a/b for rows saved before that existed.
     *
     * @return \Illuminate\Support\Collection<int, FestSchedule>
     */
    public function schedules(): \Illuminate\Support\Collection
    {
        $ids = $this->schedule_ids ?: array_values(array_filter([$this->schedule_id_a, $this->schedule_id_b]));

        return $ids === [] ? collect() : FestSchedule::with('item')->whereIn('id', $ids)->get()->sortBy('scheduled_at')->values();
    }
}
