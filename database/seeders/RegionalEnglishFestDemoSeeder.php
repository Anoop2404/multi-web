<?php

namespace Database\Seeders;

use App\Models\AcademicYearRecord;
use App\Models\FestClassCategoryScheme;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\Tenant;
use App\Services\Events\FestItemSyncService;
use App\Services\Events\FestPartitionService;
use App\Services\Events\FestRegionPartitionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RegionalEnglishFestDemoSeeder extends Seeder
{
    private const SAHODAYA_ID = '3bf5cd42-d04a-445c-bec4-92dd45f5dc28';

    private const EVENT_TITLE = 'REGIONAL ENGLISH FEST 2026-27';

    /** @var array<string, array{label: string, classes: list<int>, description: string}> */
    private const CATEGORY_GROUPS = [
        'junior' => [
            'label' => 'Junior',
            'classes' => [3, 4],
            'description' => 'English Fest junior category for Classes 3 and 4.',
        ],
        'middle' => [
            'label' => 'Middle',
            'classes' => [5, 6, 7],
            'description' => 'English Fest middle category for Classes 5 to 7.',
        ],
        'senior' => [
            'label' => 'Senior',
            'classes' => [8, 9, 10, 11, 12],
            'description' => 'English Fest senior category for Classes 8 to 12.',
        ],
    ];

    /** @var list<array<string, mixed>> */
    private const ITEMS = [
        [
            'item_code' => 'ENG-JR-STORY',
            'title' => 'Junior Storytelling',
            'class_group' => 'junior',
            'gender' => 'male',
        ],
        [
            'item_code' => 'ENG-MD-ELOC',
            'title' => 'Middle Elocution',
            'class_group' => 'middle',
            'gender' => 'female',
        ],
        [
            'item_code' => 'ENG-SR-EXT',
            'title' => 'Senior Extempore',
            'class_group' => 'senior',
            'gender' => 'open',
        ],
    ];

    public function run(): void
    {
        $sahodaya = Tenant::query()
            ->where('type', 'sahodaya')
            ->find(self::SAHODAYA_ID);

        if (! $sahodaya) {
            throw new RuntimeException('The Malappuram Sahodaya demo tenant was not found.');
        }

        $sahodaya->run(function () use ($sahodaya): void {
            DB::transaction(function () use ($sahodaya): void {
                $academicYear = AcademicYearRecord::query()->active()->first();

                if (! $academicYear) {
                    throw new RuntimeException('No active academic year is configured.');
                }

                $scheme = FestClassCategoryScheme::updateOrCreate(
                    [
                        'tenant_id' => $sahodaya->id,
                        'name' => 'English Fest 2026-27 Categories',
                    ],
                    [
                        'description' => 'Reusable class categories for the regional English Fest demo workflow.',
                        'is_default' => false,
                        'sort_order' => 100,
                    ],
                );

                foreach (self::CATEGORY_GROUPS as $key => $group) {
                    $scheme->groups()->updateOrCreate(
                        ['key' => $key],
                        [
                            'tenant_id' => $sahodaya->id,
                            'label' => $group['label'],
                            'description' => $group['description'],
                            'classes' => $group['classes'],
                            'sort_order' => array_search($key, array_keys(self::CATEGORY_GROUPS), true),
                        ],
                    );
                }

                $event = FestEvent::query()
                    ->where('tenant_id', $sahodaya->id)
                    ->where('event_type', 'english_fest')
                    ->where('academic_year_id', $academicYear->id)
                    ->whereNull('parent_event_id')
                    ->first();

                if (! $event) {
                    $event = FestEvent::create([
                        'tenant_id' => $sahodaya->id,
                        'academic_year_id' => $academicYear->id,
                        'title' => self::EVENT_TITLE,
                        'event_type' => 'english_fest',
                        'conductor_level' => 'sahodaya',
                        'conduct_levels' => ['sahodaya'],
                        'level_round' => 'sahodaya',
                    ]);
                }

                $event->update([
                    'title' => self::EVENT_TITLE,
                    'academic_year_id' => $academicYear->id,
                    'conduct_mode' => 'partitioned',
                    'combine_regions_at_finale' => false,
                    'conduct_levels' => ['sahodaya'],
                    'level_round' => 'sahodaya',
                    'registration_open' => '2026-07-28',
                    'registration_close' => '2026-08-05',
                    'event_start' => '2026-08-08',
                    'event_end' => '2026-08-09',
                    'venue' => 'Assigned regional venues',
                    'fee_type' => 'none',
                    'fee_amount' => 0,
                    'fee_settings' => [
                        'class_group_scheme' => (string) $scheme->id,
                    ],
                    'status' => 'registration_open',
                    'registration_locked' => false,
                    'verification_policy' => 'verified_only',
                    'approval_policy' => 'automatic',
                    'require_event_registration' => false,
                    'strict_item_payment_gating' => false,
                    'description' => 'Region-wise English Fest test workflow using Junior, Middle, and Senior class categories.',
                ]);

                FestEventItem::query()
                    ->where('event_id', $event->id)
                    ->where(function ($query): void {
                        $query->whereNull('item_code')
                            ->orWhereNotIn('item_code', array_column(self::ITEMS, 'item_code'));
                    })
                    ->update(['is_enabled' => false]);

                $parentItems = collect();
                foreach (self::ITEMS as $index => $itemData) {
                    $parentItems->push(FestEventItem::updateOrCreate(
                        [
                            'event_id' => $event->id,
                            'item_code' => $itemData['item_code'],
                        ],
                        [
                            'title' => $itemData['title'],
                            'category' => 'literary',
                            'stage_type' => 'off_stage',
                            'venue_type' => 'classroom',
                            'competition_format' => 'individual',
                            'duration_minutes' => 5,
                            'criteria_json' => ['partition_roles' => ['region']],
                            'participant_type' => 'individual',
                            'gender' => $itemData['gender'],
                            'class_group' => $itemData['class_group'],
                            'age_group' => 'open',
                            'max_per_school' => 1,
                            'qualify_count' => 1,
                            'owner_level' => 'sahodaya',
                            'display_order' => $index + 1,
                            'fee_amount' => 0,
                            'is_enabled' => true,
                            'is_mandatory' => false,
                        ],
                    ));
                }

                $syncResult = app(FestRegionPartitionService::class)->syncPartitionsFromRegions($event);
                $partitions = app(FestPartitionService::class)->partitions($event->fresh());
                $itemSync = app(FestItemSyncService::class);

                foreach ($partitions as $partition) {
                    $partition->update([
                        'academic_year_id' => $academicYear->id,
                        'registration_open' => $event->registration_open,
                        'registration_close' => $event->registration_close,
                        'event_start' => $event->event_start,
                        'event_end' => $event->event_end,
                        'fee_type' => 'none',
                        'fee_amount' => 0,
                        'fee_settings' => $event->fee_settings,
                        'status' => 'registration_open',
                        'registration_locked' => false,
                        'verification_policy' => 'verified_only',
                        'approval_policy' => 'automatic',
                        'require_event_registration' => false,
                        'strict_item_payment_gating' => false,
                    ]);

                    $itemSync->copyItemsToPartition($event->fresh(), $partition, 'region');

                    FestEventItem::query()
                        ->where('event_id', $partition->id)
                        ->whereNotIn('inherited_from_item_id', $parentItems->pluck('id')->all())
                        ->update(['is_enabled' => false]);

                    FestEventItem::query()
                        ->where('event_id', $partition->id)
                        ->whereIn('inherited_from_item_id', $parentItems->pluck('id')->all())
                        ->update([
                            'is_enabled' => true,
                            'fee_amount' => 0,
                        ]);
                }

                $this->command?->info(sprintf(
                    '%s ready: event %d, scheme %d, %d items, %d regional partitions, %d schools assigned.',
                    self::EVENT_TITLE,
                    $event->id,
                    $scheme->id,
                    $parentItems->count(),
                    $partitions->count(),
                    $syncResult['schools_assigned'],
                ));
            });
        });
    }
}
