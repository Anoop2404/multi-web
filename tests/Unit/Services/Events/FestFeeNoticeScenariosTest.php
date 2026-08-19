<?php

namespace Tests\Unit\Services\Events;

use App\Models\ClassCategory;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestGroup;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestSchoolEventFeeService;
use App\Services\Events\FestSportsCompositeFeeService;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Each test here reproduces one real Sahodaya fee notice pasted during development
 * (Kannur, an unnamed Sahodaya's tiered table, Wayanad, a student-count-slab example,
 * and "MCS"'s two-level notice — the same MCS case FestSchoolEventFeeService::
 * recalculateForPhase() already cites by name), asserting the engine's computed total
 * matches that notice's own worked example exactly, not just a plausible-looking number.
 */
class FestFeeNoticeScenariosTest extends TestCase
{
    use RefreshDatabase;

    private function makeSahodayaAndSchool(string $suffix = ''): array
    {
        $this->seed(SahodayaMasterDataSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => "Notice Test Sahodaya{$suffix}",
            'domain'    => Str::slug("notice-test-sahodaya{$suffix}-".Str::random(6)).'.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'         => $sahodaya->id,
            'prefix'            => 'NT'.strtoupper(Str::random(2)),
            'student_data_mode' => 'full_records',
        ]);

        $school = Tenant::create([
            'id'            => (string) Str::uuid(),
            'type'          => 'school',
            'name'          => "Notice Test School{$suffix}",
            'parent_id'     => $sahodaya->id,
            'school_prefix' => 'NS'.strtoupper(Str::random(2)),
            'is_active'     => true,
        ]);

        return compact('sahodaya', 'school');
    }

    /** Gives $school a real, resolvable fee tier via an actual SchoolClass, not the dead institution_level shortcut. */
    private function giveSchoolClassCategory(Tenant $school, string $categoryCode): SchoolClass
    {
        $categoryId = ClassCategory::whereNull('sahodaya_id')->where('code', $categoryCode)->value('id');
        return SchoolClass::create([
            'tenant_id'         => $school->id,
            'name'              => 'Test Class',
            'class_category_id'=> $categoryId,
            'is_active'         => true,
        ]);
    }

    private function approvedRegistration(FestEvent $event, FestEventItem $item, Tenant $school, ?Student $student = null): FestRegistration
    {
        $registration = FestRegistration::create([
            'event_id'  => $event->id,
            'item_id'   => $item->id,
            'school_id' => $school->id,
            'status'    => 'approved',
        ]);

        FestParticipant::create([
            'registration_id'  => $registration->id,
            'student_id'       => $student?->id,
            'participant_role' => 'performer',
        ]);

        return $registration;
    }

    /**
     * billableStudentCount() only counts participants with a real student_id/teacher_id
     * set (see its whereNotNull('student_id')->orWhereNotNull('teacher_id') guard) — a
     * participant left unlinked, as approvedRegistration() does by default, is invisible
     * to any per-student fee model. Any test that bills per student needs a real Student.
     */
    private function makeStudent(Tenant $school, string $name): Student
    {
        $class = SchoolClass::firstOrCreate(
            ['tenant_id' => $school->id, 'name' => 'Notice Test Class'],
            ['class_category_id' => ClassCategory::whereNull('sahodaya_id')->value('id'), 'is_active' => true]
        );

        return Student::create([
            'tenant_id'       => $school->id,
            'school_class_id' => $class->id,
            'name'            => $name,
            'gender'          => 'male',
            'dob'             => '2010-01-01',
            'status'          => 'active',
        ]);
    }

    /**
     * CBSE Kannur Dist Kalotsav '25: individual item = ₹250/participant
     * ("12 participants × ₹250 = ₹3,000"); group item = ₹250 event fee + ₹100/participant
     * ("7 members → 250 + (100×7) = ₹950").
     */
    public function test_kannur_individual_and_group_item_fees(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool('Kannur');

        $event = FestEvent::create([
            'tenant_id'    => $sahodaya->id,
            'title'        => 'Kannur Dist Kalotsav',
            'event_type'   => 'kalolsavam',
            'level_round'  => 'sahodaya',
            'status'       => 'registration_open',
            'fee_settings' => [
                'fee_model'                       => 'item_catalog',
                'include_school_registration'     => false,
                'default_item_fee'                => 250,
                // Pin the class-group catalog to the notice's flat ₹250 — otherwise
                // FestClassGroupScheme's own CBSE-preset default for 'hs' (₹200) wins,
                // since amountForItem() checks class_group_fees before default_item_fee.
                'class_group_fees'                 => ['hs' => 250],
                'group_item_flat_fee'              => 250,
                'group_item_per_participant_rate'  => 100,
            ],
        ]);

        $individualItem = FestEventItem::create([
            'event_id'         => $event->id,
            'title'            => 'Mono Act',
            'participant_type' => 'individual',
            'class_group'      => 'hs',
            'is_enabled'       => true,
        ]);

        for ($i = 0; $i < 12; $i++) {
            $this->approvedRegistration($event, $individualItem, $school);
        }

        $groupItem = FestEventItem::create([
            'event_id'         => $event->id,
            'title'            => 'Group Song',
            'participant_type' => 'group',
            'class_group'      => 'hs',
            'is_enabled'       => true,
        ]);

        $groupRegistration = FestRegistration::create([
            'event_id'  => $event->id,
            'item_id'   => $groupItem->id,
            'school_id' => $school->id,
            'status'    => 'approved',
        ]);

        $group = FestGroup::create(['registration_id' => $groupRegistration->id, 'team_name' => 'Team Kannur']);

        for ($i = 0; $i < 7; $i++) {
            FestParticipant::create([
                'registration_id'  => $groupRegistration->id,
                'group_id'         => $group->id,
                'participant_role' => 'performer',
            ]);
        }

        $fee = app(FestSchoolEventFeeService::class)->recalculate($event->fresh(), $school->id);

        $this->assertSame(0.0, (float) $fee->school_registration_fee);
        $this->assertSame(3950.0, (float) $fee->participation_fee, '12×250 individual + (250+100×7) group');
        $this->assertSame(3950.0, (float) $fee->total_due);
    }

    /**
     * Generic tiered-table notice: Senior Secondary ₹11,000 / Secondary ₹9,000 /
     * Other ₹6,000 school registration + ₹350 flat individual participation fee.
     * (Sahodaya membership renewal ₹5,000 deliberately excluded — separate subsystem.)
     */
    public function test_generic_tiered_table_school_registration_plus_flat_item_fee(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool('Generic');
        $this->giveSchoolClassCategory($school, 'SrSEC');

        $event = FestEvent::create([
            'tenant_id'    => $sahodaya->id,
            'title'        => 'Generic Tiered Kalotsav',
            'event_type'   => 'kalolsavam',
            'level_round'  => 'sahodaya',
            'status'       => 'registration_open',
            'fee_settings' => [
                'fee_model'                   => 'cksc_tiered',
                'include_school_registration' => true,
                'school_registration'         => [
                    'senior_secondary' => 11000,
                    'secondary'        => 9000,
                    'other'            => 6000,
                ],
                'first_item'      => 350,
                'additional_item' => 350,
            ],
        ]);

        $itemOne = FestEventItem::create(['event_id' => $event->id, 'title' => 'Item 1', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true]);
        $itemTwo = FestEventItem::create(['event_id' => $event->id, 'title' => 'Item 2', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true]);

        $this->approvedRegistration($event, $itemOne, $school);
        $this->approvedRegistration($event, $itemTwo, $school);

        $fee = app(FestSchoolEventFeeService::class)->recalculate($event->fresh(), $school->id);

        $this->assertSame(11000.0, (float) $fee->school_registration_fee, 'senior_secondary tier');
        $this->assertSame(700.0, (float) $fee->participation_fee, '2 items × flat ₹350');
        $this->assertSame(11700.0, (float) $fee->total_due);
    }

    /**
     * Wayanad Sahodaya: tiered Kalolsav registration fee (30,000/25,000/20,000 by category)
     * + Student Fee ₹250 for Phase 1 + ₹250 for Phase 2 — collected as two independently
     * payable phases (FestEventPhase::school_registration_fee_share + recalculateForPhase()),
     * full registration fee on Phase 1, ₹0 share on Phase 2, per-student fee on both.
     */
    public function test_wayanad_tiered_registration_across_two_phases(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool('Wayanad');

        $event = FestEvent::create([
            'tenant_id'          => $sahodaya->id,
            'title'              => 'Wayanad Sahodaya Kalolsav',
            'event_type'         => 'kalolsavam',
            'level_round'        => 'sahodaya',
            'status'             => 'registration_open',
            'phase_mode_enabled' => true,
            'fee_settings'       => [
                'fee_model'          => 'per_student',
                'per_student_amount' => 250,
            ],
        ]);

        $phaseOne = FestEventPhase::create([
            'event_id'                    => $event->id,
            'name'                        => 'Phase 1',
            'code'                        => 'P1',
            'sort_order'                  => 1,
            'school_registration_fee_share' => 30000,
        ]);
        $phaseTwo = FestEventPhase::create([
            'event_id'                    => $event->id,
            'name'                        => 'Phase 2',
            'code'                        => 'P2',
            'sort_order'                  => 2,
            'school_registration_fee_share' => 0,
        ]);

        $itemPhaseOne = FestEventItem::create(['event_id' => $event->id, 'title' => 'Phase 1 Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true, 'phase_id' => $phaseOne->id]);
        $itemPhaseTwo = FestEventItem::create(['event_id' => $event->id, 'title' => 'Phase 2 Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true, 'phase_id' => $phaseTwo->id]);

        $student = $this->makeStudent($school, 'Wayanad Student');
        $this->approvedRegistration($event, $itemPhaseOne, $school, $student);
        $this->approvedRegistration($event, $itemPhaseTwo, $school, $student);

        $service = app(FestSchoolEventFeeService::class);
        $feeOne = $service->recalculateForPhase($event->fresh(), $school->id, $phaseOne);
        $feeTwo = $service->recalculateForPhase($event->fresh(), $school->id, $phaseTwo);

        // ₹30,000 is the notice's senior-secondary registration amount, set directly as
        // this phase's flat share rather than re-deriving it via SchoolClassCategoryResolver
        // — tier resolution itself is already covered by the generic-tiered-table test above;
        // this test is specifically about the phase-split mechanic.
        $this->assertSame(30250.0, (float) $feeOne->total_due, '₹30,000 registration share + ₹250 student fee');
        $this->assertSame(250.0, (float) $feeTwo->total_due, '₹0 registration share + ₹250 student fee');

        // Confirmed independently payable — no gating between phases (matches
        // recalculateForPhase()'s own docblock).
        $this->assertTrue($feeOne->isFullyPaid() === false && $feeTwo->isFullyPaid() === false);
    }

    /**
     * Student-count-slab notice: school fee stepped by total registered students
     * (1-49=₹6,000, 50-99=₹8,000, 100-149=₹10,000, 150+=₹12,000), plus a separate
     * flat ₹450/student example quoted alongside it.
     */
    public function test_student_count_slab_bands_and_flat_per_student_alternative(): void
    {
        $service = app(FestSchoolEventFeeService::class);
        $slabSchedule = [
            'student_count_slabs' => [
                ['min_count' => 1, 'max_count' => 49, 'amount' => 6000],
                ['min_count' => 50, 'max_count' => 99, 'amount' => 8000],
                ['min_count' => 100, 'max_count' => 149, 'amount' => 10000],
                ['min_count' => 150, 'max_count' => null, 'amount' => 12000],
            ],
        ];

        $this->assertSame(6000.0, $service->studentCountSlabFee(30, $slabSchedule));
        $this->assertSame(8000.0, $service->studentCountSlabFee(75, $slabSchedule));
        $this->assertSame(10000.0, $service->studentCountSlabFee(120, $slabSchedule));
        $this->assertSame(12000.0, $service->studentCountSlabFee(200, $slabSchedule));

        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool('PerStudent');

        $event = FestEvent::create([
            'tenant_id'    => $sahodaya->id,
            'title'        => 'Per-Student Kalotsav',
            'event_type'   => 'kalolsavam',
            'level_round'  => 'sahodaya',
            'status'       => 'registration_open',
            'fee_settings' => ['fee_model' => 'per_student', 'per_student_amount' => 450],
        ]);

        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true]);
        foreach (range(1, 3) as $i) {
            $this->approvedRegistration($event, $item, $school, $this->makeStudent($school, "Student {$i}"));
        }

        $fee = $service->recalculate($event->fresh(), $school->id);
        $this->assertSame(1350.0, (float) $fee->total_due, '3 students × ₹450');
    }

    /**
     * "MCS" notice: Level 1 (School Registration + Off Stage + Digifest) = ₹4,000;
     * Level 2 (Sargadhara + District level events) = no separate registration fee.
     * The exact scenario FestSchoolEventFeeService::recalculateForPhase() already
     * references by name ("e.g. MCS: full amount on 'Level 1', 0 on 'Level 2'").
     */
    public function test_mcs_two_level_registration_notice(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool('MCS');

        $event = FestEvent::create([
            'tenant_id'          => $sahodaya->id,
            'title'              => 'MCS Digifest, Off Stage, Sargadhara and District Level Events',
            'event_type'         => 'kalolsavam',
            'level_round'        => 'sahodaya',
            'status'             => 'registration_open',
            'phase_mode_enabled' => true,
            'fee_settings'       => ['fee_model' => 'none'],
        ]);

        $levelOne = FestEventPhase::create([
            'event_id'                     => $event->id,
            'name'                         => 'Level 1',
            'code'                         => 'L1',
            'sort_order'                   => 1,
            'school_registration_fee_share' => 4000,
        ]);
        $levelTwo = FestEventPhase::create([
            'event_id'                     => $event->id,
            'name'                         => 'Level 2',
            'code'                         => 'L2',
            'sort_order'                   => 2,
            'school_registration_fee_share' => 0,
        ]);

        $offStageItem = FestEventItem::create(['event_id' => $event->id, 'title' => 'Off Stage Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true, 'phase_id' => $levelOne->id]);
        $sargadharaItem = FestEventItem::create(['event_id' => $event->id, 'title' => 'Sargadhara Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true, 'phase_id' => $levelTwo->id]);

        $this->approvedRegistration($event, $offStageItem, $school);
        $this->approvedRegistration($event, $sargadharaItem, $school);

        $service = app(FestSchoolEventFeeService::class);
        $feeLevelOne = $service->recalculateForPhase($event->fresh(), $school->id, $levelOne);
        $feeLevelTwo = $service->recalculateForPhase($event->fresh(), $school->id, $levelTwo);

        $this->assertSame(4000.0, (float) $feeLevelOne->total_due);
        // Zero even though the school has registered activity under Level 2 — proving
        // it's the configured ₹0 share driving this, not merely absence of registration.
        $this->assertSame(0.0, (float) $feeLevelTwo->total_due);
    }

    /**
     * Kochi Metro Sahodaya: Senior Secondary ₹8,000 / Secondary ₹7,000 / Other ₹7,000
     * school registration + ₹100 student registration with 1 included item, ₹100 extra item.
     */
    public function test_kochi_metro_sahodaya_composite_fee_notice(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool('Kochi');
        $this->giveSchoolClassCategory($school, 'SrSEC');

        $preset = config('fest_fees.presets.kochi_metro');

        $event = FestEvent::create([
            'tenant_id'    => $sahodaya->id,
            'title'        => 'Kochi Metro Sahodaya Kalolsav',
            'event_type'   => 'kalolsavam',
            'level_round'  => 'sahodaya',
            'status'       => 'registration_open',
            'fee_settings' => $preset,
        ]);

        $itemOne = FestEventItem::create(['event_id' => $event->id, 'title' => 'Light Music', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true]);
        $itemTwo = FestEventItem::create(['event_id' => $event->id, 'title' => 'Recitation', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true]);

        $student = $this->makeStudent($school, 'Kochi Student');
        // Student attends 2 items (1 included + 1 extra item @ ₹100)
        $this->approvedRegistration($event, $itemOne, $school, $student);
        $this->approvedRegistration($event, $itemTwo, $school, $student);

        $fee = app(FestSchoolEventFeeService::class)->recalculate($event->fresh(), $school->id);

        // ₹8,000 (Senior Sec Reg) + ₹100 (Student Reg with 1 item included) + ₹100 (2nd item fee) = ₹8,200
        $this->assertSame(8000.0, (float) $fee->school_registration_fee, 'Senior Secondary Tier');
        $this->assertSame(200.0, (float) $fee->participation_fee, '₹100 student reg + ₹100 extra item');
        $this->assertSame(8200.0, (float) $fee->total_due);
    }

    /**
     * Wayanad Sahodaya: Secondary school with <= 300 students falls back to 'other' tier (₹20,000)
     * instead of 'secondary' (₹25,000).
     */
    public function test_wayanad_secondary_student_threshold_fallback_to_other(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool('Wayanad Small');
        $schoolClass = $this->giveSchoolClassCategory($school, 'HS'); // Secondary (up to Class 10)

        // Give school 250 active students (<= 300 threshold)
        for ($i = 0; $i < 250; $i++) {
            \App\Models\Student::create([
                'tenant_id' => $school->id,
                'school_class_id' => $schoolClass->id,
                'name' => "Student {$i}",
                'admission_number' => "ADM-{$i}",
                'status' => 'active',
            ]);
        }

        $preset = config('fest_fees.presets.wayanad');
        $preset['secondary_min_students'] = 300;

        $event = FestEvent::create([
            'tenant_id'    => $sahodaya->id,
            'title'        => 'Wayanad Sahodaya Kalolsav',
            'event_type'   => 'kalolsavam',
            'level_round'  => 'sahodaya',
            'status'       => 'registration_open',
            'fee_settings' => $preset,
        ]);

        $tier = \App\Support\SchoolClassCategoryResolver::feeTierFor($school, $preset);
        $this->assertSame('other', $tier);
    }

    /**
     * Malabar Sahodaya notice: "School fee slab also uses unique student count" — the school fee
     * is stepped by total registered students (1-49=₹6,000 ... 150+=₹12,000) AND separately
     * ₹450/student is charged, both at once. recalculate()'s 'student_count_slab' participation
     * arm deliberately adds (studentCount × per_student_amount) on top of the slab amount when
     * per_student_amount is also configured — confirming the two DO combine in one fee_model.
     */
    public function test_malabar_slab_and_per_student_fee_combine_in_one_fee_model(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool('Malabar');

        $preset = config('fest_fees.presets.malabar');

        $event = FestEvent::create([
            'tenant_id'    => $sahodaya->id,
            'title'        => 'Malabar Sahodaya Kalolsav',
            'event_type'   => 'kalolsavam',
            'level_round'  => 'sahodaya',
            'status'       => 'registration_open',
            'fee_settings' => $preset,
        ]);

        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true]);
        foreach (range(1, 30) as $i) {
            $this->approvedRegistration($event, $item, $school, $this->makeStudent($school, "Student {$i}"));
        }

        $fee = app(FestSchoolEventFeeService::class)->recalculate($event->fresh(), $school->id);

        // Slab band for 30 students (1-49 → ₹6,000) + (30 × ₹450 per-student) = ₹19,500.
        $this->assertSame(19500.0, (float) $fee->total_due);
    }

    /**
     * MCS's actual full structure (school registration + per-student + 1 included item, ₹50/extra —
     * i.e. kalolsavam_composite's shape) split across "Level 1"/"Level 2" phases. Distinct from
     * test_mcs_two_level_registration_notice() above, which only exercised the flat registration
     * share by itself. The included-item quota and per-student fee are computed ONCE across the
     * whole event (not reset per phase — a deliberate decision, harder than the alternative, made
     * so a student's item position/quota is consistent regardless of which phase an item is in),
     * then attributed back to whichever phase each student's first item / each item itself belongs
     * to — see FestSportsCompositeFeeService::calculate()'s phase_attribution and
     * FestSchoolEventFeeService::compositeAttributionForPhase().
     */
    public function test_mcs_composite_shaped_fee_splits_correctly_across_phases_once_per_event(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool('MCSFull');

        $event = FestEvent::create([
            'tenant_id'          => $sahodaya->id,
            'title'              => 'MCS Full Structure',
            'event_type'         => 'kalolsavam',
            'level_round'        => 'sahodaya',
            'status'             => 'registration_open',
            'phase_mode_enabled' => true,
            'fee_settings'       => [
                'fee_model'                    => 'kalolsavam_composite',
                'school_registration_flat'     => 4000,
                'per_student_amount'           => 350,
                'included_items_per_student'   => 1,
                'default_item_fee'             => 50,
            ],
        ]);

        $levelOne = FestEventPhase::create(['event_id' => $event->id, 'name' => 'Level 1', 'code' => 'L1', 'sort_order' => 1, 'school_registration_fee_share' => 4000]);
        $levelTwo = FestEventPhase::create(['event_id' => $event->id, 'name' => 'Level 2', 'code' => 'L2', 'sort_order' => 2, 'school_registration_fee_share' => 0]);

        $offStageItem = FestEventItem::create(['event_id' => $event->id, 'title' => 'Off Stage Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true, 'phase_id' => $levelOne->id]);
        $sargadharaItem = FestEventItem::create(['event_id' => $event->id, 'title' => 'Sargadhara Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true, 'phase_id' => $levelTwo->id]);

        $student = $this->makeStudent($school, 'MCS Student');
        // Registered in order: Off Stage (Level 1) first, Sargadhara (Level 2) second — the
        // student's 1 included item is their Level 1 item; the Level 2 item is the 2nd, beyond
        // the quota, so it's charged the ₹50 extra-item fee.
        $this->approvedRegistration($event, $offStageItem, $school, $student);
        $this->approvedRegistration($event, $sargadharaItem, $school, $student);

        $service = app(FestSchoolEventFeeService::class);
        $feeLevelOne = $service->recalculateForPhase($event->fresh(), $school->id, $levelOne);
        $feeLevelTwo = $service->recalculateForPhase($event->fresh(), $school->id, $levelTwo);

        // Level 1: ₹4,000 share + ₹350 student-reg (attributed here — the student's earliest item).
        $this->assertSame(4350.0, (float) $feeLevelOne->total_due);
        // Level 2: ₹0 share + ₹50 extra-item fee (the 2nd, beyond-quota item, attributed to its own phase).
        $this->assertSame(50.0, (float) $feeLevelTwo->total_due);
    }

    /**
     * Proves the quota is genuinely global across the whole event, not reset per phase: a
     * student uses up their 2-item quota entirely within Level 1, so their 3rd item — in
     * Level 2 — is correctly charged the extra-item fee. Under the rejected "reset quota per
     * phase" alternative, that 3rd item would wrongly be Level 2's own "1st" item and be waived
     * for free instead.
     */
    public function test_composite_phase_quota_is_global_not_reset_per_phase(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool('QuotaGlobal');

        $event = FestEvent::create([
            'tenant_id'          => $sahodaya->id,
            'title'              => 'Quota Global Test Event',
            'event_type'         => 'kalolsavam',
            'level_round'        => 'sahodaya',
            'status'             => 'registration_open',
            'phase_mode_enabled' => true,
            'fee_settings'       => [
                'fee_model'                    => 'kalolsavam_composite',
                'school_registration_flat'     => 5000,
                'per_student_amount'           => 200,
                'included_items_per_student'   => 2,
                'default_item_fee'             => 75,
            ],
        ]);

        $levelOne = FestEventPhase::create(['event_id' => $event->id, 'name' => 'Level 1', 'code' => 'L1', 'sort_order' => 1, 'school_registration_fee_share' => 5000]);
        $levelTwo = FestEventPhase::create(['event_id' => $event->id, 'name' => 'Level 2', 'code' => 'L2', 'sort_order' => 2, 'school_registration_fee_share' => 0]);

        $itemA = FestEventItem::create(['event_id' => $event->id, 'title' => 'Item A', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true, 'phase_id' => $levelOne->id]);
        $itemB = FestEventItem::create(['event_id' => $event->id, 'title' => 'Item B', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true, 'phase_id' => $levelOne->id]);
        $itemC = FestEventItem::create(['event_id' => $event->id, 'title' => 'Item C', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true, 'phase_id' => $levelTwo->id]);

        $student = $this->makeStudent($school, 'Quota Student');
        $this->approvedRegistration($event, $itemA, $school, $student);
        $this->approvedRegistration($event, $itemB, $school, $student);
        $this->approvedRegistration($event, $itemC, $school, $student);

        $service = app(FestSchoolEventFeeService::class);
        $feeLevelOne = $service->recalculateForPhase($event->fresh(), $school->id, $levelOne);
        $feeLevelTwo = $service->recalculateForPhase($event->fresh(), $school->id, $levelTwo);

        // Level 1: ₹5,000 share + ₹200 student-reg (both quota items, A and B, are free here).
        $this->assertSame(5200.0, (float) $feeLevelOne->total_due);
        // Level 2: ₹0 share + ₹75 extra-item fee for Item C — the 3rd item overall, beyond the
        // event-wide quota of 2, NOT waived as if it were Level 2's own "first" item.
        $this->assertSame(75.0, (float) $feeLevelTwo->total_due);
    }

    /**
     * Proves nothing is double-billed or dropped: with two students split across two phases
     * (one straddling both, one only in the second), the sum of each phase's participation_fee
     * equals exactly what a single, non-phased calculate() call reports for the whole event.
     */
    public function test_composite_phase_totals_sum_to_whole_event_calculate(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool('CrossPhaseSum');

        $event = FestEvent::create([
            'tenant_id'          => $sahodaya->id,
            'title'              => 'Cross Phase Sum Test Event',
            'event_type'         => 'kalolsavam',
            'level_round'        => 'sahodaya',
            'status'             => 'registration_open',
            'phase_mode_enabled' => true,
            'fee_settings'       => [
                'fee_model'                    => 'kalolsavam_composite',
                'school_registration_flat'     => 3000,
                'per_student_amount'           => 200,
                'included_items_per_student'   => 1,
                'default_item_fee'             => 60,
            ],
        ]);

        $levelOne = FestEventPhase::create(['event_id' => $event->id, 'name' => 'Level 1', 'code' => 'L1', 'sort_order' => 1, 'school_registration_fee_share' => 3000]);
        $levelTwo = FestEventPhase::create(['event_id' => $event->id, 'name' => 'Level 2', 'code' => 'L2', 'sort_order' => 2, 'school_registration_fee_share' => 0]);

        $itemOne = FestEventItem::create(['event_id' => $event->id, 'title' => 'Item One', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true, 'phase_id' => $levelOne->id]);
        $itemTwo = FestEventItem::create(['event_id' => $event->id, 'title' => 'Item Two', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true, 'phase_id' => $levelTwo->id]);

        // Student A: Level 1 item then Level 2 item — 2nd item is beyond quota, charged ₹60.
        $studentA = $this->makeStudent($school, 'Student A');
        $this->approvedRegistration($event, $itemOne, $school, $studentA);
        $this->approvedRegistration($event, $itemTwo, $school, $studentA);

        // Student B: Level 2 item only — their 1st item, free (within quota).
        $studentB = $this->makeStudent($school, 'Student B');
        $this->approvedRegistration($event, $itemTwo, $school, $studentB);

        $service = app(FestSchoolEventFeeService::class);
        $feeLevelOne = $service->recalculateForPhase($event->fresh(), $school->id, $levelOne);
        $feeLevelTwo = $service->recalculateForPhase($event->fresh(), $school->id, $levelTwo);

        // Level 1: ₹3,000 share + ₹200 (Student A's per-student fee, attributed to their 1st item).
        $this->assertSame(3200.0, (float) $feeLevelOne->total_due);
        // Level 2: ₹0 share + ₹200 (Student B's per-student fee) + ₹60 (Student A's extra item).
        $this->assertSame(260.0, (float) $feeLevelTwo->total_due);

        $schedule = app(\App\Services\Events\FestSchoolEventFeeService::class)->resolveSchedule($event->fresh());
        $composite = app(\App\Services\Events\FestSportsCompositeFeeService::class)
            ->calculate($event->fresh(), $school->id, $schedule);

        $this->assertSame(460.0, $composite['student_reg'] + $composite['extra_item']);
        $this->assertSame(
            $composite['student_reg'] + $composite['extra_item'],
            round($feeLevelOne->participation_fee + $feeLevelTwo->participation_fee, 2)
        );
    }

    /**
     * Kochi Sahodaya notice: only Secondary (₹9,000) and Higher Secondary (₹10,000) are configured
     * — no "Other" tier at all. A primary/upper-primary-only school genuinely resolves to the
     * 'other' tier (SchoolClassCategoryResolver), but schoolRegistrationAmount()'s fallback
     * (`$amounts[$tier] ?? $amounts['secondary'] ?? 0`) silently charges the Secondary rate for any
     * tier the map doesn't have an entry for — an "Other Schools" institution gets billed as if it
     * were "Secondary" rather than ₹0 or a deliberately-flagged missing rate.
     */
    public function test_kochi_sahodaya_two_tier_map_silently_falls_back_to_secondary_rate(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool('KochiTwoTier');
        $this->giveSchoolClassCategory($school, 'UP');

        $event = FestEvent::create([
            'tenant_id'    => $sahodaya->id,
            'title'        => 'Kochi Sahodaya Kalolsav',
            'event_type'   => 'kalolsavam',
            'level_round'  => 'sahodaya',
            'status'       => 'registration_open',
            'fee_settings' => [
                'fee_model'                   => 'cksc_tiered',
                'include_school_registration' => true,
                'school_registration'         => ['secondary' => 9000, 'senior_secondary' => 10000],
                'first_item'                  => 300,
                'additional_item'             => 300,
            ],
        ]);

        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true]);
        $this->approvedRegistration($event, $item, $school);

        $fee = app(FestSchoolEventFeeService::class)->recalculate($event->fresh(), $school->id);

        $this->assertSame('other', \App\Support\SchoolClassCategoryResolver::feeTierFor($school));
        $this->assertSame(9000.0, (float) $fee->school_registration_fee);
    }

    /**
     * Direct check on FestSportsCompositeFeeService::calculate() itself, isolated from the
     * FestSchoolEventFeeService::recalculateForPhase() glue layer: phase_attribution's buckets
     * must sum exactly to the existing top-level student_reg/extra_item totals, and an item
     * with no phase assigned (phase_id = null) must land in the no_phase bucket, separate from
     * by_phase — proving no student/charge is double-counted or silently dropped when phases
     * exist alongside unphased items.
     */
    public function test_composite_calculate_phase_attribution_buckets_sum_to_totals(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school] = $this->makeSahodayaAndSchool('AttributionInvariant');

        $event = FestEvent::create([
            'tenant_id'    => $sahodaya->id,
            'title'        => 'Attribution Invariant Test Event',
            'event_type'   => 'kalolsavam',
            'level_round'  => 'sahodaya',
            'status'       => 'registration_open',
            'fee_settings' => [
                'fee_model'                    => 'kalolsavam_composite',
                'school_registration_flat'     => 1000,
                'per_student_amount'           => 100,
                'included_items_per_student'   => 1,
                'default_item_fee'             => 50,
            ],
        ]);

        $phase = FestEventPhase::create(['event_id' => $event->id, 'name' => 'Only Phase', 'code' => 'P1', 'sort_order' => 1]);

        $itemPhased = FestEventItem::create(['event_id' => $event->id, 'title' => 'Phased Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true, 'phase_id' => $phase->id]);
        $itemNoPhase = FestEventItem::create(['event_id' => $event->id, 'title' => 'Unphased Item', 'participant_type' => 'individual', 'class_group' => 'hs', 'is_enabled' => true, 'phase_id' => null]);

        // Student A: phased item first (free, quota=1), then the unphased item (2nd, extra ₹50 —
        // attributed to the CHARGED item's own phase, i.e. no_phase, regardless of A's first phase).
        $studentA = $this->makeStudent($school, 'Student A');
        $this->approvedRegistration($event, $itemPhased, $school, $studentA);
        $this->approvedRegistration($event, $itemNoPhase, $school, $studentA);

        // Student B: unphased item first (free — B's per-student fee attributes to no_phase),
        // then the phased item (2nd, extra ₹50 — attributed to that item's own phase).
        $studentB = $this->makeStudent($school, 'Student B');
        $this->approvedRegistration($event, $itemNoPhase, $school, $studentB);
        $this->approvedRegistration($event, $itemPhased, $school, $studentB);

        $schedule = app(FestSchoolEventFeeService::class)->resolveSchedule($event->fresh());
        $composite = app(FestSportsCompositeFeeService::class)->calculate($event->fresh(), $school->id, $schedule);

        $attribution = $composite['phase_attribution'];

        // student_reg: A → by_phase[phase], B → no_phase. ₹100 each, ₹200 total.
        $this->assertSame(100.0, $attribution['student_reg']['by_phase'][$phase->id]['amount']);
        $this->assertSame(1, $attribution['student_reg']['by_phase'][$phase->id]['student_count']);
        $this->assertSame(100.0, $attribution['student_reg']['no_phase']['amount']);
        $this->assertSame(0.0, $attribution['student_reg']['unattributed']['amount']);

        $sumStudentReg = $attribution['student_reg']['by_phase'][$phase->id]['amount']
            + $attribution['student_reg']['no_phase']['amount']
            + $attribution['student_reg']['unattributed']['amount'];
        $this->assertSame($composite['student_reg'], $sumStudentReg);
        $this->assertSame(200.0, $composite['student_reg']);

        // extra_item: A's 2nd item (unphased) → no_phase ₹50; B's 2nd item (phased) → by_phase ₹50.
        $this->assertSame(50.0, $attribution['extra_item']['by_phase'][$phase->id]);
        $this->assertSame(50.0, $attribution['extra_item']['no_phase']);

        $sumExtraItem = $attribution['extra_item']['by_phase'][$phase->id] + $attribution['extra_item']['no_phase'];
        $this->assertSame($composite['extra_item'], $sumExtraItem);
        $this->assertSame(100.0, $composite['extra_item']);
    }
}
