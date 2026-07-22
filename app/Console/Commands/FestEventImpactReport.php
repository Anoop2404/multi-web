<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only diagnostic: for a given fest_events id, reports how many rows in
 * every related table would be affected if this event (and, for a sports
 * season hub, its child sport events) were deleted or hidden — registrations,
 * participants, marks, attendance, fees, certificates, config, etc.
 *
 * Built to answer "what does deleting this event actually touch" before an
 * admin decides between hiding an event vs. deleting it (FestEventController
 * ::destroy() already blocks deletion when registrations exist, but doesn't
 * show the full picture across every table — this command does).
 */
class FestEventImpactReport extends Command
{
    protected $signature = 'fest:event-impact
        {event : fest_events id to inspect}
        {--sahodaya= : Sahodaya tenant id or subdomain (narrows the search if omitted)}';

    protected $description = 'Show every table/row count affected by a Fest event (and its child events, for a sports season hub) — registrations, marks, fees, config, etc.';

    /**
     * Tables that carry a direct event_id column pointing at fest_events,
     * grouped by what kind of impact deleting/hiding the event would have.
     * Table => [section, human label].
     */
    private const EVENT_ID_TABLES = [
        // Structure & config — the event's own setup, cloned by FestCloneService today.
        'fest_event_items'             => ['Structure & config', 'Competition items'],
        'fest_item_heads'              => ['Structure & config', 'Item heads (legacy sports categories)'],
        'fest_participation_policies'  => ['Structure & config', 'Participation policies'],
        'fest_participation_rules'     => ['Structure & config', 'Participation rules'],
        'fest_combination_rules'       => ['Structure & config', 'Combination rules'],
        'fest_grade_configs'           => ['Structure & config', 'Grade configs'],
        'fest_point_rules'             => ['Structure & config', 'Point rules'],
        'fest_rank_points'             => ['Structure & config', 'Rank point tables'],
        'fest_venues'                  => ['Structure & config', 'Venues'],
        'fest_volunteers'              => ['Structure & config', 'Volunteers'],
        'fest_event_staff'             => ['Structure & config', 'Event staff'],
        'fest_competition_areas'       => ['Structure & config', 'Competition areas'],
        'fest_stages'                  => ['Structure & config', 'Stages'],
        'fest_event_school_partitions' => ['Structure & config', 'School region partitions'],
        'fest_mark_criteria'           => ['Structure & config', 'Mark-entry judge criteria'],
        'id_card_templates'            => ['Structure & config', 'Custom ID card templates'],

        // Registrations — real school-submitted data.
        'fest_registrations'      => ['Registrations', 'Registrations'],
        'fest_level_registrations' => ['Registrations', 'Level/promotion registrations'],

        // Competition-day operations.
        'fest_attendance'              => ['Competition day', 'Attendance records'],
        'fest_schedules'               => ['Competition day', 'Schedule slots'],
        'fest_judge_assignments'       => ['Competition day', 'Judge assignments'],
        'fest_judge_scores'            => ['Competition day', 'Judge scores'],
        'fest_mark_sheet_uploads'      => ['Competition day', 'Uploaded signed mark sheets'],
        'fest_clash_requests'         => ['Competition day', 'Schedule clash requests'],
        'fest_substitution_requests'  => ['Competition day', 'Substitution requests'],
        'fest_school_verifications'   => ['Competition day', 'School verifications'],

        // Marks & results.
        'fest_marks'                          => ['Marks & results', 'Marks'],
        'fest_results'                         => ['Marks & results', 'Results'],
        'fest_qualifications'                  => ['Marks & results', 'Qualifications'],
        'fest_qualification_lot_draws'         => ['Marks & results', 'Tiebreak lot draws'],
        'fest_individual_championship_points'  => ['Marks & results', 'Individual championship points'],
        'fest_houses'                          => ['Marks & results', 'Houses'],
        'fest_house_schools'                   => ['Marks & results', 'House-school links'],
        'fest_record_breaks'                   => ['Marks & results', 'Record breaks'],
        'fest_athletic_records'                => ['Marks & results', 'Athletic records'],
        // This table's FK is source_event_id, not event_id — see the 'column'
        // override below, or whereIn('event_id', ...) errors with "column
        // does not exist" (confirmed against the live schema).
        'fest_state_submission_outbox'         => ['Marks & results', 'State submission outbox', 'source_event_id'],

        // Finance.
        'fest_school_event_fees' => ['Finance', 'School event fee records'],
        'fest_event_invoices'    => ['Finance', 'Invoices'],
        'fest_catering_orders'   => ['Finance', 'Catering orders'],
        'fest_food_coupons'      => ['Finance', 'Food coupons'],

        // Appeals.
        'fest_appeals' => ['Appeals', 'Appeals'],
    ];

    public function handle(): int
    {
        $eventId = (int) $this->argument('event');
        $sahodayaOpt = $this->option('sahodaya');

        $tenants = Tenant::query()
            ->where('type', 'sahodaya')
            ->when($sahodayaOpt, function ($q) use ($sahodayaOpt) {
                $q->where(function ($inner) use ($sahodayaOpt) {
                    $inner->where('id', $sahodayaOpt)->orWhere('subdomain', $sahodayaOpt);
                });
            })
            ->get();

        if ($tenants->isEmpty()) {
            $this->error('No matching Sahodaya tenant(s). Pass --sahodaya to narrow the search.');

            return self::FAILURE;
        }

        $found = false;

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($eventId, $tenant, &$found) {
                    $event = DB::table('fest_events')->where('id', $eventId)->first();
                    if (! $event) {
                        return;
                    }

                    $found = true;
                    $this->reportForEvent($tenant, $event);
                });
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$tenant->name}: {$e->getMessage()}");
            } finally {
                if (function_exists('tenancy') && tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        if (! $found) {
            $this->error("No fest_events row with id={$eventId} found in any matching Sahodaya.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function reportForEvent(Tenant $tenant, object $event): void
    {
        $this->info("Sahodaya: {$tenant->name} ({$tenant->id})");
        $this->line("Event: [{$event->id}] {$event->title} ({$event->event_type}, status={$event->status})");

        // Resolve the full tree: this event plus every descendant (sports
        // season hub → child sport events). Walked breadth-first in case a
        // future structure ever nests more than one level deep.
        $ids = [(int) $event->id];
        $frontier = [(int) $event->id];
        while ($frontier !== []) {
            $children = DB::table('fest_events')->whereIn('parent_event_id', $frontier)->pluck('id')->all();
            $children = array_diff($children, $ids);
            if ($children === []) {
                break;
            }
            $ids = array_merge($ids, $children);
            $frontier = $children;
        }

        if (count($ids) > 1) {
            $childIds = array_diff($ids, [(int) $event->id]);
            $childTitles = DB::table('fest_events')
                ->whereIn('id', $childIds)
                ->pluck('title', 'id');
            $childLabels = collect($childIds)
                ->map(fn ($id) => "[{$id}] " . ($childTitles[$id] ?? '(untitled)'))
                ->implode(', ');

            $this->line('This is a season hub with ' . count($childIds) . " child event(s): {$childLabels}");
        }

        $rows = [];
        $total = 0;

        foreach (self::EVENT_ID_TABLES as $table => $meta) {
            [$section, $label] = $meta;
            $column = $meta[2] ?? 'event_id';

            if (! $this->tableExists($table) || ! $this->columnExists($table, $column)) {
                continue;
            }

            $count = DB::table($table)->whereIn($column, $ids)->count();
            $rows[] = [$section, $label, $count];
            $total += $count;
        }

        // fest_participants has no event_id column of its own — it hangs off
        // fest_registrations via registration_id, so it's counted via a join.
        if ($this->tableExists('fest_participants') && $this->tableExists('fest_registrations')) {
            $participantCount = DB::table('fest_participants')
                ->join('fest_registrations', 'fest_registrations.id', '=', 'fest_participants.registration_id')
                ->whereIn('fest_registrations.event_id', $ids)
                ->count();
            $rows[] = ['Registrations', 'Participants (via registrations)', $participantCount];
            $total += $participantCount;
        }

        // Staff/admin scoped specifically to this event — access impact, not data loss.
        if ($this->tableExists('school_user_event_scopes')) {
            $scopeCount = DB::table('school_user_event_scopes')->whereIn('event_id', $ids)->count();
            $rows[] = ['Access & scope', 'Staff scoped to this event', $scopeCount];
            $total += $scopeCount;
        }

        usort($rows, fn ($a, $b) => $a[0] <=> $b[0] ?: $b[2] <=> $a[2]);

        $this->table(['Section', 'Table / data', 'Rows affected'], array_map(
            fn ($r) => [$r[0], $r[1], $r[2] > 0 ? "<fg=yellow>{$r[2]}</>" : $r[2]],
            $rows,
        ));

        $registrationsRow = collect($rows)->first(fn ($r) => $r[1] === 'Registrations');
        $hasRealData = collect($rows)->contains(fn ($r) => $r[0] !== 'Structure & config' && $r[2] > 0);

        $this->newLine();
        $this->line("Total rows across all tables: {$total}");

        if ($hasRealData) {
            $this->warn('This event has real registration/competition/finance data. Deleting it is destructive and irreversible — hide it from schools instead (nav_hidden), or clear the specific "Registrations"/"Competition day"/"Marks & results"/"Finance" rows above first if you truly intend to wipe it.');
        } else {
            $this->info('No registration/competition/finance data found — only "Structure & config" rows (if any) would be affected, so this event is safe to delete.');
        }
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];

        return $cache[$table] ??= \Illuminate\Support\Facades\Schema::hasTable($table);
    }

    /**
     * Guards against schema drift (a table existing but not having the
     * expected FK column, e.g. fest_state_submission_outbox's real column
     * is source_event_id, not event_id) — skip rather than crash.
     */
    private function columnExists(string $table, string $column): bool
    {
        static $cache = [];

        return $cache["{$table}.{$column}"] ??= \Illuminate\Support\Facades\Schema::hasColumn($table, $column);
    }
}
