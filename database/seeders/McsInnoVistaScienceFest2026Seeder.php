<?php

namespace Database\Seeders;

use App\Models\AcademicYearRecord;
use App\Models\FestClassCategoryScheme;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipationPolicy;
use App\Models\Tenant;
use App\Support\FestItemCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class McsInnoVistaScienceFest2026Seeder extends Seeder
{
    public const EVENT_TITLE = "InnoVista '26 — MCS SCIENCE & TECHNOLOGY EXPO 2026–27";

    public const CATEGORY_GROUPS = [
        'lp' => [
            'key' => 'lp',
            'label' => 'Category I (Classes III & IV)',
            'classes' => [3, 4],
            'description' => 'Lower Primary — Classes 3 and 4',
        ],
        'up' => [
            'key' => 'up',
            'label' => 'Category II (Classes V, VI & VII)',
            'classes' => [5, 6, 7],
            'description' => 'Upper Primary — Classes 5, 6, and 7',
        ],
        'hs' => [
            'key' => 'hs',
            'label' => 'Category III (Classes VIII, IX & X)',
            'classes' => [8, 9, 10],
            'description' => 'High School — Classes 8, 9, and 10',
        ],
        'hss' => [
            'key' => 'hss',
            'label' => 'Category IV (Classes XI & XII)',
            'classes' => [11, 12],
            'description' => 'Higher Secondary — Classes 11 and 12',
        ],
    ];

    public function run(?string $sahodayaId = null): void
    {
        $sahodayaQuery = Tenant::query()->where('type', 'sahodaya');
        if ($sahodayaId) {
            $sahodaya = $sahodayaQuery->find($sahodayaId);
        } else {
            $sahodaya = $sahodayaQuery->where('name', 'like', '%Malappuram%')->first()
                ?? $sahodayaQuery->first();
        }

        if (! $sahodaya) {
            $this->command?->warn('No Sahodaya tenant found to seed InnoVista Science Fest into.');

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
                        'name' => 'InnoVista Science Fest Categories (Cat I–IV)',
                    ],
                    [
                        'description' => 'Category I (III-IV), Category II (V-VII), Category III (VIII-X), Category IV (XI-XII)',
                        'is_default' => false,
                        'sort_order' => 110,
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
                        'event_type' => 'science_fest',
                        'conductor_level' => 'sahodaya',
                        'conduct_levels' => ['sahodaya'],
                        'level_round' => 'sahodaya',
                        'venue' => 'Nazareth Senior Secondary School, Manjeri',
                        'event_start' => '2026-10-24 08:30:00',
                        'event_end' => '2026-10-24 17:30:00',
                        'reg_start' => '2026-10-01 00:00:00',
                        'reg_end' => '2026-10-10 23:59:59',
                        'registration_open' => '2026-10-01 00:00:00',
                        'registration_close' => '2026-10-10 23:59:59',
                        'fee_type' => 'per_student',
                        'fee_amount' => 250,
                        'fee_settings' => [
                            'fee_model' => 'per_student',
                            'school_registration_flat' => 3000,
                            'per_student_amount' => 250,
                            'payment_instructions' => 'Pay the total registration fee at the host school.',
                            'class_group_scheme' => (string) $scheme->id,
                        ],
                        'status' => 'registration_open',
                        'registration_locked' => false,
                        'description' => 'Malappuram Central Sahodaya presents InnoVista \'26 – MCS Science & Technology Expo 2026–27. Theme: "Innovating Today for a Sustainable Tomorrow". Venue: Nazareth Senior Secondary School, Manjeri.',
                    ],
                );

                // 3. Configure participation policy: Rule 4 — 1 event per student
                FestParticipationPolicy::updateOrCreate(
                    [
                        'event_id' => $event->id,
                        'class_group' => null,
                    ],
                    [
                        'tenant_id' => $sahodaya->id,
                        'scope' => 'event',
                        'max_total_per_student' => 1,
                        'one_entry_per_item_per_school' => true,
                        'count_submitted_registrations' => true,
                        'is_active' => true,
                    ],
                );

                // 4. Seed all 31 items from the official MCS catalog
                $items = FestItemCatalog::mcsScienceFestItems();
                foreach ($items as $index => $itemData) {
                    FestEventItem::updateOrCreate(
                        [
                            'event_id' => $event->id,
                            'item_code' => $itemData['item_code'],
                        ],
                        [
                            'title' => $itemData['title'],
                            'category' => $itemData['category'] ?? 'science',
                            'stage_type' => $itemData['stage_type'] ?? 'off_stage',
                            'participant_type' => $itemData['participant_type'] ?? 'individual',
                            'class_group' => $itemData['class_group'] ?? 'open',
                            'gender' => $itemData['gender'] ?? 'mixed',
                            'duration_minutes' => $itemData['duration_minutes'] ?? 10,
                            'max_per_school' => $itemData['max_per_school'] ?? 1,
                            'min_group_size' => $itemData['min_group_size'] ?? 1,
                            'max_group_size' => $itemData['max_group_size'] ?? 1,
                            'criteria_json' => $itemData['criteria_json'] ?? null,
                            'is_enabled' => true,
                            'display_order' => $index + 1,
                        ],
                    );
                }

                $this->command?->info(sprintf(
                    '%s configured successfully for tenant %s (%s) with %d competition items.',
                    self::EVENT_TITLE,
                    $sahodaya->name,
                    $sahodaya->id,
                    count($items),
                ));
            });
        });
    }
}
