<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** When and where one item is held. */
class StateItemSchedule extends StateModel
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'state_item_schedules';

    protected $fillable = [
        'state_event_id', 'state_id', 'item_id', 'item_code', 'scheduled_on',
        'reporting_at', 'starts_at', 'ends_at', 'venue_id', 'duration_minutes', 'is_public', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_on' => 'date',
            'is_public'    => 'boolean',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(StateVenue::class, 'venue_id');
    }

    public function isScheduled(): bool
    {
        return $this->scheduled_on !== null && $this->starts_at !== null;
    }

    /** Minutes from midnight, for overlap arithmetic that does not care about dates. */
    public function startMinutes(): ?int
    {
        return $this->starts_at ? $this->toMinutes($this->starts_at) : null;
    }

    /**
     * End of the slot. Uses the explicit finish when one is set; otherwise the duration, which is
     * what makes two items comparable at all — an item with no end cannot be said to overlap.
     */
    public function endMinutes(): ?int
    {
        if ($this->ends_at) {
            return $this->toMinutes($this->ends_at);
        }

        $start = $this->startMinutes();

        return $start === null ? null : $start + (int) ($this->duration_minutes ?: 60);
    }

    private function toMinutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($time, 0, 5)));

        return ($h * 60) + $m;
    }
}
