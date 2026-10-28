<?php

namespace App\Console\Commands;

use App\Models\ExamEngineBooking;
use App\Services\ExamEngine\ExamEngineClient;
use Illuminate\Console\Command;

/**
 * Print / check automatable Stage A/B capacity gates from ONLINE_EXAM_RUNBOOK.md §8.
 * Does not run load/chaos (needs DO infra); exits non-zero when required config is missing.
 */
class ExamEngineStageGates extends Command
{
    protected $signature = 'exam-engine:stage-gates
                            {--stage=A : A (2k) or B (60k) expected capacity floor}
                            {--json : Machine-readable output}';

    protected $description = 'Check exam-engine Stage A/B config gates (capacity, force threshold, bridge)';

    public function handle(ExamEngineClient $client): int
    {
        $stage = strtoupper((string) $this->option('stage'));
        $expectedCapacity = $stage === 'B' ? 60000 : 2000;

        $checks = [];
        $fail = 0;

        $capacity = (int) config('exam_engine.capacity');
        $checks[] = $this->gate(
            'EXAM_ENGINE_CAPACITY',
            $capacity >= $expectedCapacity,
            "capacity={$capacity} (Stage {$stage} expects ≥ {$expectedCapacity})",
            $capacity < $expectedCapacity ? 'Raise EXAM_ENGINE_CAPACITY only after runbook ramp approval.' : null,
        );
        if ($capacity < $expectedCapacity) {
            $fail++;
        }

        $force = (int) config('exam_engine.force_engine_threshold');
        $checks[] = $this->gate(
            'EXAM_ENGINE_FORCE_THRESHOLD',
            $force > 0 && $force <= $capacity,
            "force_engine_threshold={$force}",
            $force <= 0 ? 'Set EXAM_ENGINE_FORCE_THRESHOLD so large cohorts prefer the Go engine.' : null,
        );
        if ($force <= 0 || $force > $capacity) {
            $fail++;
        }

        $scaleOut = (int) config('exam_engine.scale_out_threshold');
        $checks[] = $this->gate(
            'EXAM_ENGINE_SCALE_OUT_THRESHOLD',
            $scaleOut > 0,
            "scale_out_threshold={$scaleOut}",
        );

        $configured = $client->isConfigured();
        $checks[] = $this->gate(
            'EXAM_ENGINE_INTERNAL_URL + TOKEN',
            $configured,
            $configured ? 'bridge configured' : 'bridge not configured',
            $configured ? null : 'Set EXAM_ENGINE_INTERNAL_URL and EXAM_ENGINE_INTERNAL_TOKEN to arm exams.',
        );
        // Missing bridge is a soft fail for local smoke, hard for Stage gate.
        if (! $configured) {
            $fail++;
        }

        $public = (string) config('exam_engine.public_url');
        $checks[] = $this->gate(
            'EXAM_ENGINE_PUBLIC_URL',
            filled($public),
            filled($public) ? $public : 'missing',
        );

        $keys = filled(config('exam_engine.launch_secret_key'));
        $checks[] = $this->gate(
            'EXAM_ENGINE_LAUNCH_SECRET_KEY',
            $keys,
            $keys ? 'set' : 'missing (php artisan exam-engine:keys)',
        );

        $armed = ExamEngineBooking::query()->where('status', 'armed')->count();
        $checks[] = $this->gate(
            'armed_bookings',
            true,
            "{$armed} armed booking(s) currently",
        );

        $payload = [
            'stage' => $stage,
            'expected_capacity' => $expectedCapacity,
            'ok' => $fail === 0,
            'failed' => $fail,
            'checks' => $checks,
            'manual_blockers' => [
                'Load test Stage '.$stage.' (§6 ONLINE_EXAM_RUNBOOK.md) needs DO generators / BLR1',
                'Chaos D1–D4 needs multi-node + Redis/Sentinel infra',
                'Dry run ≥200 real students is an ops exercise',
            ],
            'commands' => [
                'php artisan exam-engine:stage-gates --stage=A',
                'php artisan exam-engine:plan-capacity --hours=72',
                'scripts/exam-day-preflight.sh --expect-nodes 1',
            ],
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info("Exam engine Stage {$stage} gates");
            foreach ($checks as $check) {
                $mark = $check['ok'] ? 'OK  ' : 'FAIL';
                $this->line("[{$mark}] {$check['id']}: {$check['detail']}");
                if (! empty($check['hint'])) {
                    $this->line("       → {$check['hint']}");
                }
            }
            $this->newLine();
            $this->warn('Manual / infra (not checked here):');
            foreach ($payload['manual_blockers'] as $b) {
                $this->line(' - '.$b);
            }
        }

        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{id: string, ok: bool, detail: string, hint: ?string} */
    private function gate(string $id, bool $ok, string $detail, ?string $hint = null): array
    {
        return compact('id', 'ok', 'detail', 'hint');
    }
}
