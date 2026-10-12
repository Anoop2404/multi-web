<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventSchoolPartition;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FestRevertToSingleEvent extends Command
{
    protected $signature = 'fest:revert-to-single
        {event : Root fest event ID to revert to single / standard conduct mode}
        {--sahodaya= : Sahodaya tenant ID or subdomain (optional if single match)}
        {--dry-run : Preview changes without writing}
        {--force : Apply without confirmation}';

    protected $description = 'Revert an event to single/standard mode, merge or clean up child partition events and registrations';

    public function handle(): int
    {
        $eventId = (int) $this->argument('event');
        $sahodayaOpt = $this->option('sahodaya');
        $isDryRun = (bool) $this->option('dry-run');
        $isForce = (bool) $this->option('force');

        $event = FestEvent::find($eventId);
        if (! $event) {
            $this->error("Event #{$eventId} not found.");

            return self::FAILURE;
        }

        if ($sahodayaOpt) {
            $sahodaya = Tenant::query()
                ->where('type', 'sahodaya')
                ->where(fn ($q) => $q->where('id', $sahodayaOpt)->orWhere('subdomain', $sahodayaOpt))
                ->first();

            if (! $sahodaya) {
                $this->error("Sahodaya tenant '{$sahodayaOpt}' not found.");

                return self::FAILURE;
            }

            if ($event->tenant_id !== $sahodaya->id) {
                $this->error("Event #{$eventId} belongs to tenant {$event->tenant_id}, not {$sahodaya->id}.");

                return self::FAILURE;
            }
        }

        if ($event->parent_event_id !== null) {
            $this->error("Event #{$eventId} is a child partition, not a root event. Specify the root event ID (#{$event->parent_event_id}).");

            return self::FAILURE;
        }

        $children = FestEvent::where('parent_event_id', $event->id)->get();
        $childIds = $children->pluck('id')->all();

        $this->info("Root Event: #{$event->id} — '{$event->title}' (conduct_mode: {$event->conduct_mode})");
        $this->info('Child partitions found: '.count($children));

        foreach ($children as $c) {
            $regCount = FestRegistration::where('event_id', $c->id)->count();
            $itemCount = FestEventItem::where('event_id', $c->id)->count();
            $this->line(" • Child #{$c->id}: '{$c->title}' — {$regCount} registration(s), {$itemCount} item(s)");
        }

        $schoolPartitionsCount = FestEventSchoolPartition::where('event_id', $event->id)->count();
        $this->info("School partition mappings: {$schoolPartitionsCount}");

        $childRegistrations = FestRegistration::whereIn('event_id', $childIds)->get();

        if ($isDryRun) {
            $this->comment('--- DRY RUN: No database changes will be made ---');
            $this->line("Would update Event #{$event->id} conduct_mode to 'standard'.");
            $this->line('Would relocate/merge '.count($childRegistrations).' child registration(s) to root Event #'.$event->id.'.');
            $this->line('Would remove '.count($children).' child partition event(s) and their inherited items.');
            $this->line("Would remove {$schoolPartitionsCount} school partition mapping(s).");

            return self::SUCCESS;
        }

        if (! $isForce && ! $this->confirm("Are you sure you want to revert Event #{$event->id} to Single ('standard') and remove its child partitions?", true)) {
            $this->comment('Aborted.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($event, $children, $childIds, $childRegistrations) {
            // Relocate or merge child registrations
            foreach ($childRegistrations as $childReg) {
                $parentReg = FestRegistration::where('event_id', $event->id)
                    ->where('school_id', $childReg->school_id)
                    ->first();

                if ($parentReg) {
                    // School is registered on both — move participants to parent reg
                    FestParticipant::where('registration_id', $childReg->id)
                        ->update([
                            'registration_id' => $parentReg->id,
                            'event_id'        => $event->id,
                        ]);
                    $childReg->delete();
                } else {
                    // Move registration directly to root event
                    $childReg->update(['event_id' => $event->id]);
                    FestParticipant::where('registration_id', $childReg->id)
                        ->update(['event_id' => $event->id]);
                }
            }

            if (! empty($childIds)) {
                // Remove items copied to child partitions
                FestEventItem::whereIn('event_id', $childIds)->delete();

                // Remove child partitions
                FestEvent::whereIn('id', $childIds)->delete();
            }

            // Remove school partition assignments for this event
            FestEventSchoolPartition::where('event_id', $event->id)->delete();

            // Set root event to standard
            $event->update([
                'conduct_mode'              => 'standard',
                'combine_regions_at_finale' => true,
            ]);
        });

        $this->info("Event #{$event->id} ('{$event->title}') successfully reverted to Single ('standard').");
        $this->info('Child partitions and redundant school partition mappings have been cleaned up.');

        return self::SUCCESS;
    }
}
