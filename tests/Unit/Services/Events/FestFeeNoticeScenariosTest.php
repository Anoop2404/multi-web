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
    private function giveSchoolClassCategory(Tenant $school, string $categoryCode): void
    {
        $categoryId = ClassCategory::whereNull('sahodaya_id')->where('code', $categoryCode)->value('id');
        SchoolClass::create([
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
}
