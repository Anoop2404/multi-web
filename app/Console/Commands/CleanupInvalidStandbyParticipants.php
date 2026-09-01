<?php

namespace App\Console\Commands;

use App\Models\FestParticipant;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * One-off cleanup for a bug in FestRegistrationItemRow.vue (showStandbyPicker):
 * the school-admin "Standbys" picker showed up for any group/team item even when
 * the item had no "Max Substitutes" configured in criteria_json (the check read
 * item.max_subs/item.standbys — fields never populated on the serialized item —
 * and silently fell back to "show it anyway" for group items). Schools could
 * register a standby participant on items that were never meant to allow one.
 * The picker bug itself is fixed separately; this only cleans up standby
 * participants that already got in under the old behavior.
 *
 * Dry-run by default — lists what would be removed. Pass --force to actually
 * delete the standby FestParticipant rows (leaves the rest of the registration,
 * i.e. the performer(s), untouched).
 */
class CleanupInvalidStandbyParticipants extends Command
{
    protected $signature = 'fest:cleanup-invalid-standbys
        {event : fest_event_items/fest_registrations event_id to clean up}
        {--sahodaya= : Sahodaya tenant id or subdomain}
        {--force : Actually delete the standby participant rows (default is dry-run)}';

    protected $description = 'Remove standby FestParticipant rows registered on items with no configured standby slots';

    public function handle(): int
    {
        $eventId = (int) $this->argument('event');
        $sahodayaOpt = $this->option('sahodaya');
        $force = (bool) $this->option('force');

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

        if ($tenants->count() > 1 && ! $sahodayaOpt) {
            $this->error('Multiple Sahodaya tenants found — pass --sahodaya=<id-or-subdomain> to target one.');

            return self::FAILURE;
        }

        $tenant = $tenants->first();
        $totalRemoved = 0;

        try {
            $tenant->run(function () use ($eventId, $force, $tenant, &$totalRemoved) {
                $rows = FestParticipant::where('participant_role', 'standby')
                    ->where('event_id', $eventId)
                    ->whereHas('registration.item', function ($q) {
                        $q->whereNull('criteria_json->standbys')
                            ->orWhere('criteria_json->standbys', 0);
                    })
                    ->with(['registration.item', 'registration.school:id,name', 'student:id,name'])
                    ->get();

                if ($rows->isEmpty()) {
                    $this->info("Sahodaya {$tenant->name}: no invalid standby participants found for event_id={$eventId}.");

                    return;
                }

                $this->info("Sahodaya {$tenant->name}: found {$rows->count()} invalid standby participant(s) for event_id={$eventId}".($force ? ' — deleting.' : ' (dry-run, pass --force to delete).'));

                $this->table(
                    ['participant_id', 'registration_id', 'reg_status', 'school', 'item', 'item_id', 'student'],
                    $rows->map(fn ($p) => [
                        $p->id,
                        $p->registration_id,
                        $p->registration->status,
                        $p->registration->school->name ?? $p->registration->school_id,
                        $p->registration->item->title ?? '?',
                        $p->registration->item_id,
                        $p->student->name ?? '?',
                    ])->all()
                );

                if ($force) {
                    foreach ($rows as $row) {
                        $row->delete();
                        $totalRemoved++;
                    }
                    $this->info("Removed {$totalRemoved} standby participant row(s).");
                }
            });
        } finally {
            if (function_exists('tenancy') && tenancy()->initialized) {
                tenancy()->end();
            }
        }

        return self::SUCCESS;
    }
}
