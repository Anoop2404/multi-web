<?php

namespace App\Console\Commands;

use App\Models\McqExam;
use App\Models\Tenant;
use App\Services\ExamEngine\ExamEngineBookingService;
use App\Services\ExamEngine\ExamEngineBridge;
use App\Services\ExamEngine\ExamEngineRun;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Arms engine exams automatically when check-in opens (plan §4.3) and refreshes their roster
 * while check-in runs, so late approvals still get in. Scheduled every 5 minutes.
 */
class ExamEngineArmDue extends Command
{
    protected $signature = 'exam-engine:arm-due {--dry-run}';

    protected $description = 'Arm online engine exams whose check-in window has opened, and refresh armed rosters';

    public function handle(ExamEngineBridge $bridge, ExamEngineBookingService $bookings): int
    {
        Tenant::where('type', 'sahodaya')->where('is_active', true)->each(function (Tenant $sahodaya) use ($bridge, $bookings) {
            TenancyDatabase::runWhenDatabaseReady($sahodaya, function () use ($bridge, $bookings) {
                McqExam::whereIn('status', ['published', 'ongoing'])
                    ->where('delivery_mode', 'online')
                    ->whereNotNull('scheduled_at')
                    ->where('scheduled_at', '>', now())
                    ->get()
                    ->filter(fn (McqExam $exam) => ExamEngineRun::usesEngine($exam) && ExamEngineRun::state($exam) !== 'closed')
                    ->each(function (McqExam $exam) use ($bridge, $bookings) {
                        if (! $this->option('dry-run')) {
                            $bookings->sync($exam); // keeps the platform capacity calendar current
                        }
                        $checkinOpens = $exam->scheduled_at->copy()->subHours((int) ExamEngineRun::settings($exam)['checkin_hours']);
                        if (now()->lt($checkinOpens)) {
                            return;
                        }
                        if ($this->option('dry-run')) {
                            $this->line("Would arm exam {$exam->id} ({$exam->title})");

                            return;
                        }
                        try {
                            if (ExamEngineRun::state($exam) === 'armed') {
                                $count = $bridge->syncRoster($exam);
                                $this->line("Refreshed roster of exam {$exam->id}: {$count} candidates");

                                return;
                            }
                            $result = $bridge->arm($exam);
                            $this->info("Armed exam {$exam->id}: {$result['candidates']} candidates, paper v{$result['version']}");
                        } catch (ValidationException $e) {
                            $this->warn("Exam {$exam->id} not armed: ".implode(' ', collect($e->errors())->flatten()->all()));
                        } catch (\Throwable $e) {
                            $this->error("Exam {$exam->id} failed to arm: {$e->getMessage()}");
                            report($e);
                        }
                    });
            });
        });

        return self::SUCCESS;
    }
}
