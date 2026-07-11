<?php

namespace App\Console\Commands;

use App\Models\McqExam;
use App\Models\Tenant;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;

/**
 * Auto-transition Talent Search exam lifecycle from registration / result windows.
 *
 * Rules (only when the relevant date columns are set):
 * - published → ongoing when registration_opens_at is reached (or scheduled_at if opens_at null and scheduled_at reached)
 * - published|ongoing stay open until registration_closes_at; after close, leave status (schools still see published/ongoing for ops)
 *   — when registration_closes_at passes and scheduled_at has passed, move published → ongoing
 * - on/after result_date: cue results by leaving a settings_json flag; do not auto-publish scores
 * - completed is never auto-set (manual)
 */
class TransitionMcqExamWindows extends Command
{
    protected $signature = 'mcq:transition-exam-windows';

    protected $description = 'Open/close Talent Search registration and cue results from exam window dates';

    public function handle(): int
    {
        $opened = 0;
        $cued = 0;
        $now = now();

        $sahodayas = Tenant::query()->sahodayas()->where('is_active', true)->get();

        foreach ($sahodayas as $sahodaya) {
            TenancyDatabase::runWhenDatabaseReady($sahodaya, function () use ($now, &$opened, &$cued) {
                McqExam::query()
                    ->whereIn('status', ['draft', 'published', 'ongoing'])
                    ->where(function ($q) {
                        $q->whereNotNull('registration_opens_at')
                            ->orWhereNotNull('registration_closes_at')
                            ->orWhereNotNull('result_date')
                            ->orWhereNotNull('scheduled_at');
                    })
                    ->chunkById(50, function ($exams) use ($now, &$opened, &$cued) {
                        foreach ($exams as $exam) {
                            $dirty = false;

                            // Open registration: draft/published → published when opens_at reached.
                            if ($exam->registration_opens_at
                                && $now->gte($exam->registration_opens_at)
                                && in_array($exam->status, ['draft', 'published'], true)
                                && (! $exam->registration_closes_at || $now->lte($exam->registration_closes_at))
                            ) {
                                if ($exam->status === 'draft' && $exam->hasFee()) {
                                    $exam->status = 'published';
                                    $dirty = true;
                                    $opened++;
                                } elseif ($exam->status === 'draft' && ($exam->exam_type ?? '') === 'practice') {
                                    $exam->status = 'published';
                                    $dirty = true;
                                    $opened++;
                                }
                            }

                            // Move to ongoing once the exam is scheduled / registration closed.
                            if (in_array($exam->status, ['published'], true)) {
                                $shouldOngoing = false;
                                if ($exam->scheduled_at && $now->gte($exam->scheduled_at)) {
                                    $shouldOngoing = true;
                                }
                                if ($exam->registration_closes_at && $now->gt($exam->registration_closes_at)) {
                                    $shouldOngoing = true;
                                }
                                if ($shouldOngoing) {
                                    $exam->status = 'ongoing';
                                    $dirty = true;
                                }
                            }

                            // Cue results on result_date (does not auto-publish — Sahodaya still reviews).
                            if ($exam->result_date
                                && $now->toDateString() >= $exam->result_date->toDateString()
                                && ! $exam->results_published
                            ) {
                                $settings = $exam->settings_json ?? [];
                                if (empty($settings['results_ready_cued_at'])) {
                                    $settings['results_ready_cued_at'] = $now->toIso8601String();
                                    $exam->settings_json = $settings;
                                    $dirty = true;
                                    $cued++;
                                }
                            }

                            if ($dirty) {
                                $exam->save();
                            }
                        }
                    });
            });
        }

        $this->info("Opened {$opened} exam(s); cued results for {$cued} exam(s).");

        return self::SUCCESS;
    }
}
