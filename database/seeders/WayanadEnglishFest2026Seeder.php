<?php

namespace Database\Seeders;

use App\Models\AcademicYearRecord;
use App\Models\FestClassCategoryScheme;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\Tenant;
use App\Services\Events\FestParticipationPolicyService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WayanadEnglishFest2026Seeder extends Seeder
{
    public const EVENT_TITLE = 'WAYANAD SAHODAYA ENGLISH LANGUAGE FEST – 2026';

    public const CATEGORY_GROUPS = [
        'kiddies' => [
            'label' => 'Kiddies (Classes I & II)',
            'classes' => [1, 2],
            'description' => 'English Fest Kiddies for Classes 1 and 2.',
        ],
        'category_1' => [
            'label' => 'Category 1 (Classes III & IV)',
            'classes' => [3, 4],
            'description' => 'English Fest Category 1 for Classes 3 and 4.',
        ],
        'category_2' => [
            'label' => 'Category 2 (Classes V, VI & VII)',
            'classes' => [5, 6, 7],
            'description' => 'English Fest Category 2 for Classes 5, 6, and 7.',
        ],
        'category_3' => [
            'label' => 'Category 3 (Classes VIII, IX & X)',
            'classes' => [8, 9, 10],
            'description' => 'English Fest Category 3 for Classes 8, 9, and 10.',
        ],
        'category_4' => [
            'label' => 'Category 4 (Classes XI & XII)',
            'classes' => [11, 12],
            'description' => 'English Fest Category 4 for Classes 11 and 12.',
        ],
    ];

    /** @var list<array<string, mixed>> */
    public const FEST_ITEMS = [
        // KIDDIES (CLASSES I & II)
        [
            'class_group' => 'kiddies',
            'item_code' => 'ENG-KID-01',
            'title' => 'Storytelling',
            'participant_type' => 'individual',
            'stage_type' => 'on_stage',
            'max_per_school' => 2,
        ],
        [
            'class_group' => 'kiddies',
            'item_code' => 'ENG-KID-02',
            'title' => 'Art of Articulation',
            'participant_type' => 'individual',
            'stage_type' => 'on_stage',
            'max_per_school' => 2,
        ],
        [
            'class_group' => 'kiddies',
            'item_code' => 'ENG-KID-03',
            'title' => 'Choral Reading',
            'participant_type' => 'group',
            'stage_type' => 'on_stage',
            'max_per_school' => 1,
            'min_group_size' => 4,
            'max_group_size' => 10,
        ],
        [
            'class_group' => 'kiddies',
            'item_code' => 'ENG-KID-04',
            'title' => 'Choral Recitation',
            'participant_type' => 'group',
            'stage_type' => 'on_stage',
            'max_per_school' => 1,
            'min_group_size' => 4,
            'max_group_size' => 10,
        ],

        // CATEGORY 1 (CLASSES III & IV)
        [
            'class_group' => 'category_1',
            'item_code' => 'ENG-C1-01',
            'title' => 'Spelling Marathon',
            'participant_type' => 'individual',
            'stage_type' => 'off_stage',
            'max_per_school' => 2,
        ],
        [
            'class_group' => 'category_1',
            'item_code' => 'ENG-C1-02',
            'title' => 'Phonics',
            'participant_type' => 'individual',
            'stage_type' => 'off_stage',
            'max_per_school' => 2,
        ],
        [
            'class_group' => 'category_1',
            'item_code' => 'ENG-C1-03',
            'title' => 'Handwriting',
            'participant_type' => 'individual',
            'stage_type' => 'off_stage',
            'max_per_school' => 2,
        ],
        [
            'class_group' => 'category_1',
            'item_code' => 'ENG-C1-04',
            'title' => 'Art of Articulation',
            'participant_type' => 'individual',
            'stage_type' => 'on_stage',
            'max_per_school' => 2,
        ],
        [
            'class_group' => 'category_1',
            'item_code' => 'ENG-C1-05',
            'title' => 'Be The Character',
            'participant_type' => 'pair',
            'stage_type' => 'on_stage',
            'max_per_school' => 1,
            'min_group_size' => 2,
            'max_group_size' => 2,
        ],
        [
            'class_group' => 'category_1',
            'item_code' => 'ENG-C1-06',
            'title' => 'Choral Reading',
            'participant_type' => 'group',
            'stage_type' => 'on_stage',
            'max_per_school' => 1,
            'min_group_size' => 4,
            'max_group_size' => 10,
        ],

        // CATEGORY 2 (CLASSES V, VI, & VII)
        [
            'class_group' => 'category_2',
            'item_code' => 'ENG-C2-01',
            'title' => 'Listening Comprehension',
            'participant_type' => 'individual',
            'stage_type' => 'off_stage',
            'max_per_school' => 2,
        ],
        [
            'class_group' => 'category_2',
            'item_code' => 'ENG-C2-02',
            'title' => 'News Reading',
            'participant_type' => 'individual',
            'stage_type' => 'on_stage',
            'max_per_school' => 2,
        ],
        [
            'class_group' => 'category_2',
            'item_code' => 'ENG-C2-03',
            'title' => 'Product Launch',
            'participant_type' => 'individual',
            'stage_type' => 'on_stage',
            'max_per_school' => 2,
        ],
        [
            'class_group' => 'category_2',
            'item_code' => 'ENG-C2-04',
            'title' => 'Art of Articulation',
            'participant_type' => 'individual',
            'stage_type' => 'on_stage',
            'max_per_school' => 2,
        ],
        [
            'class_group' => 'category_2',
            'item_code' => 'ENG-C2-05',
            'title' => 'News Writing',
            'participant_type' => 'pair',
            'stage_type' => 'off_stage',
            'max_per_school' => 1,
            'min_group_size' => 2,
            'max_group_size' => 2,
        ],
        [
            'class_group' => 'category_2',
            'item_code' => 'ENG-C2-06',
            'title' => 'Art of Storytelling',
            'participant_type' => 'group',
            'stage_type' => 'on_stage',
            'max_per_school' => 1,
            'min_group_size' => 3,
            'max_group_size' => 8,
        ],

        // CATEGORY 3 (CLASSES VIII, IX, X)
        [
            'class_group' => 'category_3',
            'item_code' => 'ENG-C3-01',
            'title' => 'Translation',
            'participant_type' => 'individual',
            'stage_type' => 'off_stage',
            'max_per_school' => 2,
        ],
        [
            'class_group' => 'category_3',
            'item_code' => 'ENG-C3-02',
            'title' => 'Product Launch',
            'participant_type' => 'individual',
            'stage_type' => 'on_stage',
            'max_per_school' => 2,
        ],
        [
            'class_group' => 'category_3',
            'item_code' => 'ENG-C3-03',
            'title' => 'Pros and Cons',
            'participant_type' => 'individual',
            'stage_type' => 'on_stage',
            'max_per_school' => 2,
        ],
        [
            'class_group' => 'category_3',
            'item_code' => 'ENG-C3-04',
            'title' => 'Art of Articulation',
            'participant_type' => 'individual',
            'stage_type' => 'on_stage',
            'max_per_school' => 2,
        ],
        [
            'class_group' => 'category_3',
            'item_code' => 'ENG-C3-05',
            'title' => 'The Masked Interviewee',
            'participant_type' => 'pair',
            'stage_type' => 'on_stage',
            'max_per_school' => 1,
            'min_group_size' => 2,
            'max_group_size' => 2,
        ],
        [
            'class_group' => 'category_3',
            'item_code' => 'ENG-C3-06',
            'title' => 'Art of Story Telling',
            'participant_type' => 'group',
            'stage_type' => 'on_stage',
            'max_per_school' => 1,
            'min_group_size' => 3,
            'max_group_size' => 8,
        ],

        // CATEGORY 4 (CLASSES XI & XII)
        [
            'class_group' => 'category_4',
            'item_code' => 'ENG-C4-01',
            'title' => 'Mock Interview',
            'participant_type' => 'individual',
            'stage_type' => 'on_stage',
            'max_per_school' => 2,
        ],
        [
            'class_group' => 'category_4',
            'item_code' => 'ENG-C4-02',
            'title' => 'Live Reporting',
            'participant_type' => 'individual',
            'stage_type' => 'on_stage',
            'max_per_school' => 2,
        ],
        [
            'class_group' => 'category_4',
            'item_code' => 'ENG-C4-03',
            'title' => 'Translation',
            'participant_type' => 'individual',
            'stage_type' => 'off_stage',
            'max_per_school' => 2,
        ],
        [
            'class_group' => 'category_4',
            'item_code' => 'ENG-C4-04',
            'title' => 'Stand Up Comedy',
            'participant_type' => 'individual',
            'stage_type' => 'on_stage',
            'max_per_school' => 2,
        ],
        [
            'class_group' => 'category_4',
            'item_code' => 'ENG-C4-05',
            'title' => 'Art of Articulation',
            'participant_type' => 'individual',
            'stage_type' => 'on_stage',
            'max_per_school' => 2,
        ],
        [
            'class_group' => 'category_4',
            'item_code' => 'ENG-C4-06',
            'title' => 'Debate',
            'participant_type' => 'group',
            'stage_type' => 'on_stage',
            'max_per_school' => 1,
            'min_group_size' => 2,
            'max_group_size' => 4,
        ],
    ];

    public function run(?string $sahodayaId = null): void
    {
        $sahodayaQuery = Tenant::query()->where('type', 'sahodaya');
        if ($sahodayaId) {
            $sahodaya = $sahodayaQuery->find($sahodayaId);
        } else {
            $sahodaya = $sahodayaQuery->where('name', 'like', '%Wayanad%')->first()
                ?? $sahodayaQuery->first();
        }

        if (! $sahodaya) {
            $this->command?->warn('No Sahodaya tenant found to seed Wayanad English Fest into.');

            return;
        }

        $sahodaya->run(function () use ($sahodaya): void {
            DB::transaction(function () use ($sahodaya): void {
                $academicYear = AcademicYearRecord::query()->active()->first()
                    ?? AcademicYearRecord::query()->first();

                if (! $academicYear) {
                    $academicYear = AcademicYearRecord::create([
                        'tenant_id' => $sahodaya->id,
                        'name' => '2026-27',
                        'is_active' => true,
                        'start_date' => '2026-06-01',
                        'end_date' => '2027-03-31',
                    ]);
                }

                // 1. Create or update class category scheme
                $scheme = FestClassCategoryScheme::updateOrCreate(
                    [
                        'tenant_id' => $sahodaya->id,
                        'name' => 'English Fest 2026 Categories',
                    ],
                    [
                        'description' => '5 categories: Kiddies (I-II), Cat 1 (III-IV), Cat 2 (V-VII), Cat 3 (VIII-X), Cat 4 (XI-XII)',
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

                // 2. Create or update FestEvent
                $event = FestEvent::updateOrCreate(
                    [
                        'tenant_id' => $sahodaya->id,
                        'title' => self::EVENT_TITLE,
                    ],
                    [
                        'academic_year_id' => $academicYear->id,
                        'event_type' => 'english_fest',
                        'conductor_level' => 'sahodaya',
                        'conduct_levels' => ['sahodaya'],
                        'level_round' => 'sahodaya',
                        'venue' => 'HILL BLOOMS SCHOOL, MANANTHAVADY',
                        'event_start' => '2026-10-30 08:00:00',
                        'event_end' => '2026-10-31 17:00:00',
                        'fee_type' => 'per_student',
                        'fee_amount' => 250,
                        'fee_settings' => [
                            'fee_model' => 'per_student',
                            'per_student_amount' => 250,
                            'class_group_scheme' => (string) $scheme->id,
                        ],
                        'status' => 'registration_open',
                        'registration_locked' => false,
                        'description' => 'WAYANAD SAHODAYA SCHOOLS COMPLEX ENGLISH LANGUAGE FEST– 2026 at HILL BLOOMS SCHOOL, MANANTHAVADY',
                    ],
                );

                // 3. Apply the dynamic language fest participation policy preset
                app(FestParticipationPolicyService::class)->applyPresetToEvent($event, 'sahodaya_language_fest');

                // 4. Create all 28 items
                foreach (self::FEST_ITEMS as $index => $itemData) {
                    FestEventItem::updateOrCreate(
                        [
                            'event_id' => $event->id,
                            'item_code' => $itemData['item_code'],
                        ],
                        [
                            'title' => $itemData['title'],
                            'category' => 'literary',
                            'stage_type' => $itemData['stage_type'],
                            'participant_type' => $itemData['participant_type'],
                            'class_group' => $itemData['class_group'],
                            'max_per_school' => $itemData['max_per_school'],
                            'min_group_size' => $itemData['min_group_size'] ?? ($itemData['participant_type'] === 'pair' ? 2 : 1),
                            'max_group_size' => $itemData['max_group_size'] ?? ($itemData['participant_type'] === 'pair' ? 2 : 1),
                            'is_enabled' => true,
                            'display_order' => $index + 1,
                        ],
                    );
                }

                $this->command?->info(sprintf(
                    '%s configured successfully for tenant %s with %d items.',
                    self::EVENT_TITLE,
                    $sahodaya->name,
                    count(self::FEST_ITEMS),
                ));
            });
        });
    }
}
