<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;
use Illuminate\Support\Facades\DB;

class McqSeatingService
{
    /**
     * Auto-allocate hall_room + seat_no for approved registrations.
     *
     * Halls come from exam settings_json.halls:
     *   [{ "name": "Hall A", "capacity": 40 }, ...]
     *
     * When halls are defined: fill sequentially by school then candidate name,
     * spilling into the next hall when capacity is reached.
     * When no halls: assign seat_no 1..N and leave hall_room unchanged (or "Main").
     *
     * @return array{allocated: int, halls_used: int}
     */
    public function allocateForExam(McqExam $exam, bool $reallocate = false): array
    {
        $halls = $this->normalizedHalls($exam);

        $query = McqRegistration::where('exam_id', $exam->id)
            ->where('approval_status', 'approved')
            ->where('status', '!=', 'cancelled')
            ->with(['student:id,name', 'teacher:id,name', 'school:id,name']);

        if (! $reallocate) {
            $query->where(function ($q) {
                $q->whereNull('seat_no')->orWhere('seat_no', '');
            });
        }

        $registrations = $query->get()
            ->sortBy([
                fn (McqRegistration $r) => strtolower((string) ($r->school?->name ?? '')),
                fn (McqRegistration $r) => strtolower($r->participantName()),
            ])
            ->values();

        if ($registrations->isEmpty()) {
            return ['allocated' => 0, 'halls_used' => 0];
        }

        return DB::transaction(function () use ($registrations, $halls) {
            if ($halls === []) {
                $seat = 1;
                foreach ($registrations as $registration) {
                    $registration->update([
                        'seat_no'   => (string) $seat,
                        'hall_room' => $registration->hall_room ?: 'Main',
                    ]);
                    $seat++;
                }

                return ['allocated' => $registrations->count(), 'halls_used' => 1];
            }

            $hallIndex = 0;
            $seatInHall = 0;
            $hallsUsed = 0;

            foreach ($registrations as $registration) {
                while (
                    $hallIndex < count($halls) - 1
                    && $seatInHall >= (int) $halls[$hallIndex]['capacity']
                ) {
                    $hallIndex++;
                    $seatInHall = 0;
                }

                $seatInHall++;
                if ($seatInHall === 1) {
                    $hallsUsed++;
                }

                // If over capacity on the last hall, keep assigning sequentially.
                $registration->update([
                    'hall_room' => $halls[$hallIndex]['name'],
                    'seat_no'   => (string) $seatInHall,
                ]);
            }

            return [
                'allocated'  => $registrations->count(),
                'halls_used' => $hallsUsed,
            ];
        });
    }

    /**
     * @return list<array{name: string, capacity: int}>
     */
    public function normalizedHalls(McqExam $exam): array
    {
        $settings = is_array($exam->settings_json) ? $exam->settings_json : [];
        $raw = $settings['halls'] ?? [];

        if (! is_array($raw)) {
            return [];
        }

        $halls = [];
        foreach ($raw as $hall) {
            if (! is_array($hall)) {
                continue;
            }
            $name = trim((string) ($hall['name'] ?? $hall['room'] ?? ''));
            $capacity = (int) ($hall['capacity'] ?? 0);
            if ($name === '' || $capacity < 1) {
                continue;
            }
            $halls[] = ['name' => $name, 'capacity' => $capacity];
        }

        return $halls;
    }

    /**
     * Persist hall definitions onto the exam settings_json.
     *
     * @param  list<array{name?: string, capacity?: int}>  $halls
     */
    public function saveHalls(McqExam $exam, array $halls): McqExam
    {
        $normalized = [];
        foreach ($halls as $hall) {
            $name = trim((string) ($hall['name'] ?? ''));
            $capacity = (int) ($hall['capacity'] ?? 0);
            if ($name === '' || $capacity < 1) {
                continue;
            }
            $normalized[] = ['name' => $name, 'capacity' => min($capacity, 5000)];
        }

        $settings = is_array($exam->settings_json) ? $exam->settings_json : [];
        $settings['halls'] = $normalized;
        $exam->update(['settings_json' => $settings]);

        return $exam->fresh();
    }
}
