<?php

namespace App\Support\Fest;

use App\Models\FestEvent;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class FestEventDateRanges
{
    /** Validate only ranges touched by this request, including partial updates. */
    public static function validate(array $data, ?FestEvent $event = null): void
    {
        foreach (['registration_open' => 'registration_close', 'event_start' => 'event_end'] as $start => $end) {
            if (! array_key_exists($start, $data) && ! array_key_exists($end, $data)) {
                continue;
            }
            $startValue = array_key_exists($start, $data) ? $data[$start] : $event?->{$start};
            $endValue = array_key_exists($end, $data) ? $data[$end] : $event?->{$end};
            if (filled($startValue) && filled($endValue) && Carbon::parse($endValue)->lt(Carbon::parse($startValue))) {
                throw ValidationException::withMessages([
                    $end => $start === 'event_start'
                        ? 'Competition end must be on or after competition start.'
                        : 'Registration closing date must be on or after its opening date.',
                ]);
            }
        }
    }
}
