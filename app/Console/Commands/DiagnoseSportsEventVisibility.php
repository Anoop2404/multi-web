<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * php artisan fest:diagnose-school-visibility 21 23 --sahodaya=71fcc096-e12e-4eb3-bc45-ecbc9c4ab306
 *
 * Read-only. Explains, for each given fest_events id, exactly why it would or
 * wouldn't appear on the School-admin Sports Registration list — mirroring the
 * real query in FestRegistrationController::index() / FestEvent::scopeListedForSchool()
 * / FestEvent::scopeVisibleToSchool() and the "hide season hub once children
 * exist" whereNotExists clause. Makes no changes to the database.
 */
class DiagnoseSportsEventVisibility extends Command
{
    protected $signature = 'fest:diagnose-school-visibility
        {events* : One or more fest_events ids to check}
        {--sahodaya= : Sahodaya tenant id or subdomain}
        {--school= : Optional school tenant id, to check visibility for that specific school (level_round=school events)}';

    protected $description = 'Read-only: explain why a sports event is or is not visible on the School-admin registration list';

    private const SCHOOL_LIST_STATUSES = ['published', 'registration_open', 'ongoing', 'completed'];

    public function handle(): int
    {
        $eventIds = array_map('intval', $this->argument('events'));
        $sahodayaOpt = $this->option('sahodaya');
        $schoolId = $this->option('school');

        $tenants = Tenant::query()
            ->where('type', 'sahodaya')
            ->when($sahodayaOpt, function ($q) use ($sahodayaOpt) {
                $q->where(function ($inner) use ($sahodayaOpt) {
                    $inner->where('id', $sahodayaOpt)->orWhere('subdomain', $sahodayaOpt);
                });
            })
            ->get();

        if ($tenants->isEmpty()) {
            $this->error('No matching Sahodaya tenant(s). Pass --sahodaya=<id or subdomain>.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($tenant, $eventIds, $schoolId) {
                    $events = FestEvent::whereIn('id', $eventIds)->get()->keyBy('id');
                    if ($events->isEmpty()) {
                        return;
                    }

                    $this->info("Sahodaya: {$tenant->name} ({$tenant->id})");

                    // Every event that has ANY parent_event_id, tenant-wide — used to
                    // check the "hide season hub once children exist" whereNotExists
                    // clause from FestRegistrationController::index().
                    $allChildParentIds = FestEvent::whereNotNull('parent_event_id')
                        ->pluck('parent_event_id', 'id');

                    foreach ($eventIds as $id) {
                        $event = $events->get($id);
                        $this->line('');
                        $this->line("Event #{$id}" . ($event ? ": {$event->title}" : ' — NOT FOUND in this tenant'));
                        if (! $event) {
                            continue;
                        }

                        $this->line("  status               = {$event->status}");
                        $this->line("  event_type           = {$event->event_type}");
                        $this->line('  nav_hidden           = ' . ($event->nav_hidden ? 'true' : 'false'));
                        $this->line('  level_round          = ' . ($event->level_round ?? '(null)'));
                        $this->line('  conducting_school_id = ' . ($event->conducting_school_id ?? '(null)'));
                        $this->line('  parent_event_id      = ' . ($event->parent_event_id ?? '(null)'));
                        $this->line('  partition_role       = ' . ($event->partition_role ?? '(null)'));

                        $reasons = [];

                        if ($event->event_type !== 'sports') {
                            $reasons[] = "event_type is '{$event->event_type}', not 'sports' — won't appear on the Sports registration page at all (ofType() mismatch).";
                        }

                        $hasChildren = $allChildParentIds->contains($event->id);
                        if ($hasChildren) {
                            $childTitles = FestEvent::where('parent_event_id', $event->id)->pluck('title', 'id');
                            $reasons[] = 'Another event points to this one as its parent_event_id (' .
                                $childTitles->map(fn ($t, $cid) => "#{$cid} {$t}")->implode(', ') .
                                ') — the "hide season hub once children exist" rule excludes it from the school list, even though it may be a genuine standalone event. Fix with: php artisan fest:unmark-mistaken-season ' . $event->id;
                        }

                        if ($event->nav_hidden) {
                            $reasons[] = 'nav_hidden is true — excluded from every school-facing list/nav regardless of status. Often left over from a previous season-hub mix-up.';
                        }

                        if (! in_array($event->status, self::SCHOOL_LIST_STATUSES, true)) {
                            $reasons[] = "status '{$event->status}' is not in the school-visible list (" . implode(', ', self::SCHOOL_LIST_STATUSES) . ') — schools only ever see these four statuses, regardless of registration dates.';
                        }

                        $levelOk = $event->level_round === 'sahodaya' || $event->level_round === null;
                        if (! $levelOk) {
                            if ($event->level_round === 'school') {
                                if ($schoolId && $event->conducting_school_id === $schoolId) {
                                    $this->line("  (level_round='school', but conducting_school_id matches --school={$schoolId} — OK for that school.)");
                                } else {
                                    $reasons[] = "level_round is 'school', conducting_school_id={$event->conducting_school_id} — only visible to that one school" .
                                        ($schoolId ? " (does not match --school={$schoolId})" : '') . ', hidden from every other school.';
                                }
                            } else {
                                $reasons[] = "level_round is '{$event->level_round}' — not 'sahodaya'/null and not 'school', so visibleToSchool() excludes it for everyone.";
                            }
                        }

                        if ($reasons === []) {
                            $this->info('  => Should be VISIBLE on the school registration list (no exclusion condition matched).');
                        } else {
                            $this->warn('  => HIDDEN from school list. Reason(s):');
                            foreach ($reasons as $r) {
                                $this->line("     - {$r}");
                            }
                        }
                    }
                });
            } catch (\Throwable $e) {
                $this->warn("  Skipped tenant {$tenant->name}: {$e->getMessage()}");
            } finally {
                if (function_exists('tenancy') && tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        return self::SUCCESS;
    }
}
