<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventSchoolPartition;
use App\Models\FestGroup;
use App\Models\FestLevelRegistration;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\Student;
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

            // 1. Analyze Items Mapping
            $rootItems = FestEventItem::where('event_id', $event->id)->get()->keyBy('item_code');
            $childItems = FestEventItem::whereIn('event_id', $childIds)->get()->keyBy('id');
            $itemMap = [];
            $missingItems = [];
            foreach ($childItems as $cItem) {
                if ($cItem->inherited_from_item_id && FestEventItem::where('id', $cItem->inherited_from_item_id)->where('event_id', $event->id)->exists()) {
                    $itemMap[$cItem->id] = $cItem->inherited_from_item_id;
                } elseif ($rootItems->has($cItem->item_code)) {
                    $itemMap[$cItem->id] = $rootItems->get($cItem->item_code)->id;
                } else {
                    $missingItems[] = "Item #{$cItem->id} ({$cItem->item_code}: {$cItem->title})";
                }
            }

            if (! empty($missingItems)) {
                $this->error('The following child items could not be mapped to root Event #'.$event->id.':');
                foreach ($missingItems as $mi) {
                    $this->line(' - '.$mi);
                }

                return self::FAILURE;
            }

            // 2. Analyze Registrations
            $childRegistrations = FestRegistration::whereIn('event_id', $childIds)->get();
            $schoolIds = $childRegistrations->pluck('school_id')->unique()->all();
            $schools = Tenant::whereIn('id', $schoolIds)->get()->keyBy('id');

            $regRows = [];
            $activeCollisions = [];
            foreach ($childRegistrations as $cReg) {
                $schoolName = $schools->get($cReg->school_id)?->name ?? $cReg->school_id;
                $childItem = $childItems->get($cReg->item_id);
                $mappedRootItemId = $itemMap[$cReg->item_id] ?? null;
                $rootItem = $mappedRootItemId ? FestEventItem::find($mappedRootItemId) : null;
                $partCount = FestParticipant::where('registration_id', $cReg->id)->count();

                $existingRootReg = FestRegistration::where('event_id', $event->id)
                    ->where('school_id', $cReg->school_id)
                    ->where('item_id', $mappedRootItemId)
                    ->first();

                $action = $existingRootReg
                    ? "Merge participants into Root Reg #{$existingRootReg->id}"
                    : "Move Reg to Root #{$event->id}";

                if ($existingRootReg && ! in_array($cReg->status, ['withdrawn', 'rejected'], true) && ! in_array($existingRootReg->status, ['withdrawn', 'rejected'], true)) {
                    $activeCollisions[] = [
                        'school' => $schoolName,
                        'item' => ($rootItem?->item_code ?? '').' — '.($rootItem?->title ?? ''),
                        'root_reg_id' => $existingRootReg->id,
                        'child_reg_id' => $cReg->id,
                    ];
                }

                $regRows[] = [
                    $cReg->id,
                    $schoolName,
                    $childItem?->item_code ?? '—',
                    $rootItem ? ($rootItem->item_code.' (ID: '.$rootItem->id.')') : 'Unmapped',
                    $partCount,
                    $cReg->status,
                    $action,
                ];
            }

            // 3. Analyze Student Fest IDs (fest_level_registrations)
            $rootLevels = FestLevelRegistration::where('event_id', $event->id)->get()->keyBy('student_id');
            $childLevels = FestLevelRegistration::whereIn('event_id', $childIds)->get();
            $studentIds = $childLevels->pluck('student_id')->unique()->all();
            $students = Student::whereIn('id', $studentIds)->get()->keyBy('id');

            // Find used numbers on root event
            $usedNumbers = $rootLevels->pluck('registration_number')
                ->merge(FestParticipant::where('event_id', $event->id)->pluck('level_registration_number'))
                ->filter()
                ->map(fn ($n) => (string) $n);

            $usedSet = $usedNumbers->flip()->all();
            $nextNumber = 1;
            foreach ($usedNumbers as $num) {
                if (ctype_digit((string) $num)) {
                    $nextNumber = max($nextNumber, (int) $num + 1);
                }
            }

            $festIdRows = [];
            $duplicateEnrolments = 0;
            $newStudents = 0;
            $idCollisions = 0;
            $canonicalPlan = [];

            foreach ($childLevels->sortBy('id') as $cLevel) {
                $student = $students->get($cLevel->student_id);
                $studentName = $student?->name ?? 'Student #'.$cLevel->student_id;
                $schoolName = $schools->get($cLevel->school_id)?->name ?? $cLevel->school_id;
                $childNumber = (string) $cLevel->registration_number;

                if ($rootLevels->has($cLevel->student_id)) {
                    $rootNumber = (string) $rootLevels->get($cLevel->student_id)->registration_number;
                    $canonicalNumber = $rootNumber;
                    $action = "Exists on Root (#{$rootNumber}) — Consolidate duplicate";
                    $duplicateEnrolments++;
                } else {
                    if (isset($usedSet[$childNumber])) {
                        $newNumber = (string) $nextNumber++;
                        $usedSet[$newNumber] = true;
                        $canonicalNumber = $newNumber;
                        $action = "Number #{$childNumber} taken on Root — Assign #{$newNumber}";
                        $idCollisions++;
                    } else {
                        $canonicalNumber = $childNumber;
                        $usedSet[$childNumber] = true;
                        if (ctype_digit($childNumber)) {
                            $nextNumber = max($nextNumber, (int) $childNumber + 1);
                        }
                        $action = "Retain unique Fest ID #{$childNumber}";
                    }
                    $newStudents++;
                }

                $canonicalPlan[$cLevel->student_id] = $canonicalNumber;

                $festIdRows[] = [
                    $cLevel->student_id,
                    $studentName,
                    $schoolName,
                    $childNumber,
                    $canonicalNumber,
                    $action,
                ];
            }

            if ($isDryRun) {
                $this->newLine();
                $this->info('=== DRY RUN: REGISTRATION & ITEM MAPPING ===');
                $this->table(
                    ['Child Reg ID', 'School', 'Child Item', 'Root Item', 'Participants', 'Status', 'Action'],
                    $regRows
                );

                $this->newLine();
                $this->info('=== DRY RUN: STUDENT FEST ID AUDIT ===');
                if (empty($festIdRows)) {
                    $this->line('No student level registrations found on child partitions.');
                } else {
                    $this->table(
                        ['Student ID', 'Student Name', 'School', 'Child Fest ID', 'Target Fest ID', 'Status / Action'],
                        $festIdRows
                    );
                }

                $this->newLine();
                $this->info('=== SUMMARY AUDIT REPORT ===');
                $this->line(' • Total child registrations: '.count($childRegistrations));
                $this->line(' • Active duplicate registrations for same school+item: '.count($activeCollisions));
                $this->line(' • Existing Fest IDs on Root Event: '.$rootLevels->count());
                $this->line(' • Duplicate student enrolments to consolidate: '.$duplicateEnrolments);
                $this->line(' • New students being added to Root Event: '.$newStudents);
                if ($idCollisions > 0) {
                    $this->warn(' • Fest ID number collisions re-numbered: '.$idCollisions);
                } else {
                    $this->info(' • Fest ID number collisions: 0 (All numbers clean)');
                }
                $this->line(' • Next available Fest ID on Root: '.$nextNumber);
                $this->line(' • School partition mappings to delete: '.$schoolPartitionsCount);
                $this->line(' • Child partitions to detach & archive: '.count($children));
                $this->newLine();
                $this->comment('--- DRY RUN COMPLETE: No database changes were made ---');

                return self::SUCCESS;
            }

            if (! $isForce && ! $this->confirm("Are you sure you want to revert Event #{$event->id} to Single ('standard') and merge its child partitions?", true)) {
                $this->comment('Aborted.');

                return self::SUCCESS;
            }

            DB::transaction(function () use ($event, $children, $childIds, $childRegistrations, $itemMap, $canonicalPlan, $childLevels, $rootLevels) {
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
                foreach ($childLevels as $cLevel) {
                    $targetNumber = $canonicalPlan[$cLevel->student_id] ?? $cLevel->registration_number;

                    if ($rootLevels->has($cLevel->student_id)) {
                        // Consolidate duplicate level registration
                        $cLevel->delete();
                    } else {
                        // Migrate level registration to root event with canonical number
                        $cLevel->update([
                            'event_id'            => $event->id,
                            'registration_number' => $targetNumber,
                        ]);
                    }

                    // Update participant level registration number
                    FestParticipant::where('event_id', $event->id)
                        ->where('student_id', $cLevel->student_id)
                        ->update(['level_registration_number' => $targetNumber]);
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
            $this->info('Child registrations and Fest IDs have been merged into the main event and partitions cleaned up.');

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
