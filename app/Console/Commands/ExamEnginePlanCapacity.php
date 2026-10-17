<?php

namespace App\Console\Commands;

use App\Services\ExamEngine\ExamEngineBookingService;
use Illuminate\Console\Command;

/** Prints booked exam-engine slots and the engine nodes each needs, as JSON for plan-and-scale.sh. */
class ExamEnginePlanCapacity extends Command
{
    protected $signature = 'exam-engine:plan-capacity {--hours=72 : Look ahead this many hours}';

    protected $description = 'Booked online-exam slots with students at once and engine nodes needed (JSON)';

    public function handle(ExamEngineBookingService $bookings): int
    {
        $this->line(json_encode([
            'generated_at' => now()->utc()->toIso8601String(),
            'capacity'     => (int) config('exam_engine.capacity'),
            'slots'        => $bookings->plan(now()->subHours(6), now()->addHours(max(1, (int) $this->option('hours')))),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
