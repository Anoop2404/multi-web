<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventSchoolPartition;
use App\Models\FestGroup;
use App\Models\FestLevelRegistration;
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

        $execute = function () use ($eventId, $isDryRun, $isForce) {
            $event = FestEvent::find($eventId);
            if (! $event) {
                $this->error("Event #{$eventId} not found.");

                return self::FAILURE;
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

            // Build item map: child item id -> root parent item id
            $rootItems = FestEventItem::where('event_id', $event->id)->get()->keyBy('item_code');
            $childItems = FestEventItem::whereIn('event_id', $childIds)->get();
            $itemMap = [];
            foreach ($childItems as $cItem) {
                if ($cItem->inherited_from_item_id) {
                    $itemMap[$cItem->id] = $cItem->inherited_from_item_id;
                } elseif ($rootItems->has($cItem->item_code)) {
                    $itemMap[$cItem->id] = $rootItems->get($cItem->item_code)->id;
                }
            }

            if ($isDryRun) {
                $this->comment('--- DRY RUN: No database changes will be made ---');
                $this->line("Would update Event #{$event->id} conduct_mode to 'standard'.");
                $this->line('Would relocate/merge '.count($childRegistrations).' child registration(s) to root Event #'.$event->id.' (mapping child items to parent items).');
                $this->line('Would consolidate student Fest IDs (fest_level_registrations) onto root Event #'.$event->id.'.');
                $this->line('Would detach and archive '.count($children).' child partition event(s) and delete their copied items.');
                $this->line("Would remove {$schoolPartitionsCount} school partition mapping(s).");

                return self::SUCCESS;
            }

            if (! $isForce && ! $this->confirm("Are you sure you want to revert Event #{$event->id} to Single ('standard') and merge its child partitions?", true)) {
                $this->comment('Aborted.');

                return self::SUCCESS;
            }

            DB::transaction(function () use ($event, $children, $childIds, $childRegistrations, $itemMap) {
                // 1. Relocate child registrations and remap items
                foreach ($childRegistrations as $childReg) {
                    $mappedItemId = $itemMap[$childReg->item_id] ?? null;

                    $existing = FestRegistration::where('event_id', $event->id)
                        ->where('school_id', $childReg->school_id)
                        ->where('item_id', $mappedItemId)
                        ->first();

                    if ($existing) {
                        // Same school & same item already registered on root event: move participants to existing reg
                        FestParticipant::where('registration_id', $childReg->id)
                            ->update([
                                'registration_id' => $existing->id,
                                'event_id'        => $event->id,
                            ]);
                        $childReg->delete();
                    } else {
                        // Move registration to root event with mapped item
                        $childReg->update([
                            'event_id' => $event->id,
                            'item_id'  => $mappedItemId ?: $childReg->item_id,
                        ]);
                        FestParticipant::where('registration_id', $childReg->id)
                            ->update(['event_id' => $event->id]);
                    }
                }

                // 2. Consolidate level registrations (student Fest IDs)
                $rootLevels = FestLevelRegistration::where('event_id', $event->id)->get()->keyBy('student_id');
                $childLevels = FestLevelRegistration::whereIn('event_id', $childIds)->get();

                foreach ($childLevels as $cLevel) {
                    if ($rootLevels->has($cLevel->student_id)) {
                        $rLevel = $rootLevels->get($cLevel->student_id);
                        FestParticipant::where('event_id', $event->id)
                            ->where('student_id', $cLevel->student_id)
                            ->update(['level_registration_number' => $rLevel->registration_number]);
                        $cLevel->delete();
                    } else {
                        $cLevel->update(['event_id' => $event->id]);
                        $rootLevels->put($cLevel->student_id, $cLevel);
                    }
                }

                // 3. Move groups and user event scopes
                FestGroup::whereIn('event_id', $childIds)->update(['event_id' => $event->id]);
                DB::table('school_user_event_scopes')->whereIn('event_id', $childIds)->update(['event_id' => $event->id]);

                // 4. Remove school partition assignments
                FestEventSchoolPartition::where('event_id', $event->id)->delete();

                // 5. Delete child copied items
                if (! empty($childIds)) {
                    FestEventItem::whereIn('event_id', $childIds)->delete();

                    // Detach and archive child partition events safely
                    FestEvent::whereIn('id', $childIds)->update([
                        'parent_event_id' => null,
                        'conduct_mode'    => 'standard',
                        'partition_key'   => null,
                        'cluster_key'     => null,
                        'region_id'       => null,
                        'status'          => 'cancelled',
                        'nav_hidden'      => true,
                    ]);
                }

                // 6. Set root event to standard
                $event->update([
                    'conduct_mode'              => 'standard',
                    'combine_regions_at_finale' => true,
                ]);
            });

            $this->info("Event #{$event->id} ('{$event->title}') successfully reverted to Single ('standard').");
            $this->info('Child registrations have been merged into the main event and partitions cleaned up.');

            return self::SUCCESS;
        };

        if ($sahodayaOpt) {
            $sahodaya = Tenant::query()
                ->where('type', 'sahodaya')
                ->where(fn ($q) => $q->where('id', $sahodayaOpt)->orWhere('subdomain', $sahodayaOpt))
                ->first();

            if (! $sahodaya) {
                $this->error("Sahodaya tenant '{$sahodayaOpt}' not found.");

                return self::FAILURE;
            }

            $exitCode = self::FAILURE;
            $sahodaya->run(function () use ($execute, &$exitCode) {
                $exitCode = $execute();
            });

            if (function_exists('tenancy') && tenancy()->initialized) {
                tenancy()->end();
            }

            return $exitCode;
        }

        return $execute();
    }
}
