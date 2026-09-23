<?php

namespace App\Console\Commands\State;

use App\Models\FestStateProgramItem;
use App\Models\State\StateAttendance;
use App\Models\State\StateCertificate;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestMark;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateItemResult;
use App\Models\State\StateItemSchedule;
use App\Models\State\StateQualifierIntake;
use App\Models\State\StateSahodaya;
use App\Services\State\Fest\StateResultService;
use Illuminate\Console\Command;

/**
 * Phase 11 of the State Kalotsav module — the pre-cutover check.
 *
 * Two jobs. First, compare the rankings already stored against what the module computes from the same
 * marks: if the module would rank an item differently, that must be understood before the switch, not
 * discovered when a result is published. Second, report the data conditions that make the module's
 * screens misleading rather than wrong — registrations with no canonical Sahodaya, entries with no
 * chest number, items with no schedule — each of which reads on screen as "nobody is here" when the
 * truth is "this was never linked up".
 *
 * Read-only. Nothing here writes, so it is safe to run against production.
 */
class VerifyStateModule extends Command
{
    protected $signature = 'state:verify-module
        {--event= : One event id; defaults to every event}
        {--strict : Exit non-zero on warnings as well as ranking differences}';

    protected $description = 'Compare stored State rankings against the module and report data gaps before cutover';

    public function handle(StateResultService $results): int
    {
        $events = StateFestEvent::query()
            ->when($this->option('event'), fn ($q, $id) => $q->where('id', $id))
            ->orderByDesc('starts_on')->get();

        if ($events->isEmpty()) {
            $this->warn('No State events found.');

            return Command::SUCCESS;
        }

        $differences = 0;
        $warnings = 0;

        foreach ($events as $event) {
            $this->newLine();
            $this->info("── {$event->name} (#{$event->id}, {$event->status}) ".str_repeat('─', max(1, 40 - strlen($event->name))));

            $differences += $this->compareRankings($event, $results);
            $warnings += $this->reportDataGaps($event);
        }

        $this->newLine();

        if ($differences) {
            $this->error("{$differences} item(s) would be ranked differently by the module. Resolve these before switching over.");
        } else {
            $this->info('Every stored ranking matches what the module computes.');
        }

        if ($warnings) {
            $this->warn("{$warnings} data gap(s) reported above.");
        }

        return $differences || ($warnings && $this->option('strict')) ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Recompute each item from its marks and compare to the positions already stored.
     *
     * Compared as an ordered list of participant ids, not as a set: two competitors swapping first and
     * second is the difference that matters, and a set comparison would call it identical.
     */
    private function compareRankings(StateFestEvent $event, StateResultService $results): int
    {
        $items = FestStateProgramItem::where('state_program_id', $event->state_program_id)->get();
        $rows = [];
        $differences = 0;

        foreach ($items as $item) {
            $stored = StateFestMark::where('state_event_id', $event->id)
                ->whereIn('registration_id', StateFestRegistration::where('state_event_id', $event->id)
                    ->where('item_id', $item->id)->pluck('id'))
                ->whereNotNull('position')
                ->orderBy('position')->orderBy('participant_id')
                ->pluck('participant_id')->all();

            if ($stored === []) {
                continue;
            }

            // ranking(), not computeItem(): the latter persists what it works out, and a verification
            // pass that rewrites the thing it is verifying can never fail.
            $computed = $results->ranking($event, $item)
                ->sortBy(['position', 'participant_id'])
                ->pluck('participant_id')->all();

            $same = $computed === array_map('intval', $stored);
            $result = StateItemResult::where('state_event_id', $event->id)->where('item_id', $item->id)->first();

            $rows[] = [
                $item->item_code,
                count($stored),
                count($computed),
                $result?->status ?? 'none',
                $same ? 'match' : 'DIFFERS',
            ];

            if (! $same) {
                $differences++;
            }
        }

        if ($rows === []) {
            $this->line('  No ranked items yet — nothing to compare.');

            return 0;
        }

        $this->table(['Item', 'Stored', 'Module', 'Result state', 'Ranking'], $rows);

        return $differences;
    }

    /**
     * Conditions that make a screen read wrongly rather than break. Each line names the fix, because
     * "17 registrations have no Sahodaya" without "run state:backfill-sahodaya-directory" is a fact
     * nobody can act on.
     */
    private function reportDataGaps(StateFestEvent $event): int
    {
        $registrations = StateFestRegistration::where('state_event_id', $event->id);
        $approved = (clone $registrations)->where('status', 'approved');

        $checks = [
            [
                'count' => (clone $registrations)->whereNull('sahodaya_id')->count(),
                'what' => 'registration(s) with no canonical Sahodaya',
                'why' => 'They appear as "Unattributed" in every report and earn no Sahodaya points.',
                'fix' => 'php artisan state:backfill-sahodaya-directory --all',
            ],
            [
                // Intakes belong to the program, not the event — the State receives a Sahodaya's
                // submission before deciding which event it feeds.
                'count' => StateQualifierIntake::where('state_program_id', $event->state_program_id)
                    ->whereNull('sahodaya_id')->count(),
                'what' => 'qualifier intake(s) with no canonical Sahodaya',
                'why' => 'Submissions from them cannot be filtered by Sahodaya.',
                'fix' => 'php artisan state:backfill-sahodaya-directory',
            ],
            [
                'count' => StateFestParticipant::whereIn('registration_id', (clone $approved)->pluck('id'))
                    ->whereNull('chest_number')->whereNull('withdrawn_at')->count(),
                'what' => 'competing participant(s) with no chest number',
                'why' => 'Printed sheets and the judge portal identify entries by chest number; unnumbered ones sort last and cannot be scored anonymously.',
                'fix' => 'Assign numbers on the Chest Numbers tab.',
            ],
            [
                'count' => max(0, FestStateProgramItem::where('state_program_id', $event->state_program_id)
                    ->whereIn('id', (clone $approved)->distinct()->pluck('item_id'))->count()
                    - StateItemSchedule::where('state_event_id', $event->id)->distinct()->count('item_id')),
                'what' => 'item(s) with entries but no schedule',
                'why' => 'They are missing from the public schedule and print with no date or venue.',
                'fix' => 'Set dates on the Item Schedule tab.',
            ],
            [
                'count' => StateFestMark::where('state_event_id', $event->id)
                    ->whereNotIn('registration_id', StateAttendance::where('state_event_id', $event->id)
                        ->where('status', 'present')->pluck('registration_id'))
                    ->count(),
                'what' => 'mark(s) recorded for an entry not marked present',
                'why' => 'Attendance and marks disagree, so an absentee may be holding a position.',
                'fix' => 'Reconcile on the Attendance tab before publishing.',
            ],
            [
                'count' => StateCertificate::where('state_event_id', $event->id)
                    ->where('status', StateCertificate::STALE)->count(),
                'what' => 'stale certificate(s)',
                'why' => 'They were printed from a result that has since moved and will not print again until regenerated.',
                'fix' => 'Regenerate on the Certificates tab.',
            ],
            [
                'count' => StateSahodaya::query()->whereNull('state_id')->count(),
                'what' => 'directory row(s) with no state',
                'why' => 'A state-scoped operator cannot see them at all — the module fails closed.',
                'fix' => 'php artisan state:backfill-sahodaya-directory --state=KL',
            ],
        ];

        $reported = 0;

        foreach ($checks as $check) {
            if ($check['count'] === 0) {
                continue;
            }

            $this->newLine();
            $this->warn("  {$check['count']} {$check['what']}");
            $this->line("    {$check['why']}");
            $this->line("    Fix: {$check['fix']}");
            $reported++;
        }

        if ($reported === 0) {
            $this->line('  No data gaps.');
        }

        return $reported;
    }
}
