<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\SchoolRegionAssignment;
use App\Models\Tenant;
use App\Support\AcademicYear;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * docs/REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md §8.2/§8.3 — explicit repair for
 * operational rows left on a partitioned parent after conversion. Dry-run by default;
 * requires an explicit tenant + event; transactional per event; writes what it did to
 * the console (a full reversible mapping ledger table is not built here — see the
 * limitations note below before running this with --apply on real data).
 *
 * What this command does:
 *   1. Resolve each FestRegistration row that's still on the parent's own event_id.
 *   2. Resolve the school's active-year region (SchoolRegionAssignment::forYear()).
 *   3. Resolve that region's child event under the parent.
 *   4. Move the registration (and its participants, via the existing FK relationship —
 *      participants belong to the registration, not the event, so re-parenting the
 *      registration's event_id is sufficient for them) to the child event.
 *
 * What this command deliberately does NOT do, per §8.3 items 4-5 (left for a human
 * decision, not automated here):
 *   - Move schedules/attendance/marks/qualifications tied to the registration by other
 *     foreign keys (fest_schedules/fest_marks/fest_attendance key off item_id +
 *     participant_id, not registration_id directly — moving those safely needs a
 *     decision about what happens to a schedule slot/mark that already exists against
 *     the parent's own item ids vs the child's copied item ids, which this command does
 *     not attempt to resolve).
 *   - Recalculate fees/results after the move (plan step 4) — FestSchoolEventFee rows on
 *     the parent are left as-is; run fee recalculation for affected schools separately
 *     after confirming the registration move is correct.
 *   - Quarantine ambiguous rows (plan step 5) — a registration whose school has no
 *     active-year region assignment, or whose region has no matching child event, is
 *     skipped and reported, not moved or deleted. Resolve those manually.
 *
 * Run `fest:audit-event-topology` first and review its operational_rows_on_partitioned_parent
 * findings before using --apply.
 */
class FestRepairPartitionedParentOperationalRows extends Command
{
    protected $signature = 'fest:repair-partitioned-parent-operational-rows
        {--sahodaya= : Sahodaya tenant id (required)}
        {--event= : Partitioned parent fest_events id (required)}
        {--apply : Actually write changes. Without this flag, nothing is written.}';

    protected $description = 'Move FestRegistration rows stuck on a partitioned parent event to their correct region child (dry-run by default)';

    public function handle(): int
    {
        $sahodayaId = $this->option('sahodaya');
        $eventId = $this->option('event');
        $apply = (bool) $this->option('apply');

        if (! $sahodayaId || ! $eventId) {
            $this->error('Both --sahodaya and --event are required.');

            return self::FAILURE;
        }

        $tenant = Tenant::where('id', $sahodayaId)->where('type', 'sahodaya')->first();
        if (! $tenant) {
            $this->error("No sahodaya tenant found for id '{$sahodayaId}'.");

            return self::FAILURE;
        }

        $result = self::FAILURE;

        $tenant->run(function () use ($tenant, $eventId, $apply, &$result) {
            $result = $this->repairEvent($tenant, (int) $eventId, $apply);
        });

        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        return $result;
    }

    private function repairEvent(Tenant $tenant, int $eventId, bool $apply): int
    {
        $parent = FestEvent::find($eventId);
        if (! $parent) {
            $this->error("Event #{$eventId} not found.");

            return self::FAILURE;
        }

        if (($parent->conduct_mode ?? 'standard') !== 'partitioned' || $parent->parent_event_id !== null) {
            $this->error("Event #{$eventId} is not a partitioned parent/root — refusing to run.");

            return self::FAILURE;
        }

        $registrations = FestRegistration::where('event_id', $parent->id)->get();

        if ($registrations->isEmpty()) {
            $this->info("No registrations directly on parent #{$parent->id} — nothing to repair.");

            return self::SUCCESS;
        }

        $year = AcademicYear::forSahodaya($tenant->id);
        $assignments = SchoolRegionAssignment::forTenant($tenant->id)->forYear($year)->get()->keyBy('school_id');

        $regionChildren = FestEvent::where('parent_event_id', $parent->id)
            ->where('partition_role', 'region')
            ->get()
            ->keyBy('region_id');

        $moved = 0;
        $skipped = 0;

        $this->line(($apply ? '[APPLYING] ' : '[DRY-RUN] ')."Event #{$parent->id} — {$registrations->count()} registration(s) on parent, active year {$year}.");

        foreach ($registrations as $registration) {
            $assignment = $assignments->get($registration->school_id);

            if (! $assignment) {
                $this->warn("  SKIP registration #{$registration->id}: school {$registration->school_id} has no {$year} region assignment.");
                $skipped++;

                continue;
            }

            $child = $regionChildren->get($assignment->region_id);

            if (! $child) {
                $this->warn("  SKIP registration #{$registration->id}: school's region #{$assignment->region_id} has no matching child event under #{$parent->id}.");
                $skipped++;

                continue;
            }

            $this->line("  MOVE registration #{$registration->id} (school {$registration->school_id}) -> event #{$child->id} ({$child->title})");

            if ($apply) {
                DB::transaction(function () use ($registration, $child) {
                    $registration->update(['event_id' => $child->id]);
                });
            }

            $moved++;
        }

        $this->info(($apply ? 'Moved' : 'Would move')." {$moved} registration(s); skipped {$skipped}.");

        if (! $apply && $moved > 0) {
            $this->comment('Re-run with --apply to write these changes. Remember: fee recalculation and schedule/mark/attendance migration are not handled by this command — see its class docblock.');
        }

        return self::SUCCESS;
    }
}
