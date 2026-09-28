<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestParticipationPolicy;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestGradePointService;
use App\Services\Events\FestParticipationLimitService;
use App\Services\Events\FestParticipationPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestDynamicLanguageFestRulesTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(array $policyOverrides = []): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Wayanad Sahodaya Test',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'WS', 'student_data_mode' => 'counts_only']);
        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Hill Blooms School', 'domain' => Str::uuid().'.test', 'membership_status' => 'approved', 'is_active' => true,
        ]);
        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'English Language Fest 2026', 'event_type' => 'english_fest',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $policy = FestParticipationPolicy::create(array_merge([
            'tenant_id' => $sahodaya->id,
            'scope' => 'event',
            'event_id' => $event->id,
            'is_active' => true,
            'preset_key' => 'sahodaya_language_fest',
            'max_overall_per_student' => 2,
            'max_total_per_student' => 1,
            'max_pair_per_student' => 1,
            'max_group_per_student' => 1,
            'max_common_per_student' => 1,
            'pair_points_mode' => 'individual',
            'one_entry_per_item_per_school' => false,
            'count_submitted_registrations' => true,
        ], $policyOverrides));

        return [$event, $school->id, $policy];
    }

    private function registerStudent(FestEvent $event, string $schoolId, int $studentId, FestEventItem $item): FestRegistration
    {
        $reg = FestRegistration::create([
            'event_id' => $event->id,
            'item_id' => $item->id,
            'school_id' => $schoolId,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        FestParticipant::create([
            'registration_id' => $reg->id,
            'student_id' => $studentId,
            'participant_type' => 'student',
            'participant_role' => 'performer',
        ]);

        return $reg;
    }

    public function test_option_1_one_individual_and_one_group_is_allowed(): void
    {
        [$event, $schoolId] = $this->fixture();
        $studentId = 101;

        $indItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Spelling Marathon', 'item_code' => 'SPEL',
            'participant_type' => 'individual', 'stage_type' => 'off_stage', 'is_enabled' => true,
        ]);
        $grpItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Choral Reading', 'item_code' => 'CHOR',
            'participant_type' => 'group', 'stage_type' => 'on_stage', 'min_group_size' => 4, 'max_group_size' => 10, 'is_enabled' => true,
        ]);

        $this->registerStudent($event, $schoolId, $studentId, $indItem);

        $service = new FestParticipationLimitService($event);
        $errors = $service->validateRegistration($grpItem, $schoolId, [$studentId]);

        $this->assertEmpty($errors, 'Option 1 (1 individual + 1 group) must be valid');
    }

    public function test_option_2_one_pair_and_one_group_is_allowed(): void
    {
        [$event, $schoolId] = $this->fixture();
        $studentId = 102;

        $pairItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Be The Character', 'item_code' => 'PAIR1',
            'participant_type' => 'pair', 'stage_type' => 'on_stage', 'min_group_size' => 2, 'max_group_size' => 2, 'is_enabled' => true,
        ]);
        $grpItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Choral Reading', 'item_code' => 'CHOR',
            'participant_type' => 'group', 'stage_type' => 'on_stage', 'min_group_size' => 4, 'max_group_size' => 10, 'is_enabled' => true,
        ]);

        $this->registerStudent($event, $schoolId, $studentId, $pairItem);

        $service = new FestParticipationLimitService($event);
        $errors = $service->validateRegistration($grpItem, $schoolId, [$studentId]);

        $this->assertEmpty($errors, 'Option 2 (1 pair + 1 group) must be valid');
    }

    public function test_option_6_one_individual_and_one_pair_is_allowed(): void
    {
        [$event, $schoolId] = $this->fixture();
        $studentId = 103;

        $indItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Phonics', 'item_code' => 'PHON',
            'participant_type' => 'individual', 'stage_type' => 'off_stage', 'is_enabled' => true,
        ]);
        $pairItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'News Writing', 'item_code' => 'NWPR',
            'participant_type' => 'pair', 'stage_type' => 'off_stage', 'min_group_size' => 2, 'max_group_size' => 2, 'is_enabled' => true,
        ]);

        $this->registerStudent($event, $schoolId, $studentId, $indItem);

        $service = new FestParticipationLimitService($event);
        $errors = $service->validateRegistration($pairItem, $schoolId, [$studentId]);

        $this->assertEmpty($errors, 'Option 6 (1 individual + 1 pair) must be valid');
    }

    public function test_second_individual_item_is_blocked(): void
    {
        [$event, $schoolId] = $this->fixture();
        $studentId = 104;

        $ind1 = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Phonics', 'item_code' => 'PHON',
            'participant_type' => 'individual', 'stage_type' => 'off_stage', 'is_enabled' => true,
        ]);
        $ind2 = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Handwriting', 'item_code' => 'HAND',
            'participant_type' => 'individual', 'stage_type' => 'off_stage', 'is_enabled' => true,
        ]);

        $this->registerStudent($event, $schoolId, $studentId, $ind1);

        $service = new FestParticipationLimitService($event);
        $errors = $service->validateRegistration($ind2, $schoolId, [$studentId]);

        $this->assertNotEmpty($errors, 'A student cannot register for 2 individual items');
        $this->assertStringContainsString('total items', implode(' ', $errors));
    }

    public function test_second_pair_item_is_blocked(): void
    {
        [$event, $schoolId] = $this->fixture();
        $studentId = 105;

        $pair1 = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Be The Character', 'item_code' => 'PAIR1',
            'participant_type' => 'pair', 'stage_type' => 'on_stage', 'min_group_size' => 2, 'max_group_size' => 2, 'is_enabled' => true,
        ]);
        $pair2 = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'News Writing', 'item_code' => 'PAIR2',
            'participant_type' => 'pair', 'stage_type' => 'off_stage', 'min_group_size' => 2, 'max_group_size' => 2, 'is_enabled' => true,
        ]);

        $this->registerStudent($event, $schoolId, $studentId, $pair1);

        $service = new FestParticipationLimitService($event);
        $errors = $service->validateRegistration($pair2, $schoolId, [$studentId]);

        $this->assertNotEmpty($errors, 'A student cannot register for 2 pair items');
        $this->assertStringContainsString('pair items', implode(' ', $errors));
    }

    public function test_third_item_is_blocked_by_overall_cap_of_two(): void
    {
        [$event, $schoolId] = $this->fixture();
        $studentId = 106;

        $indItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Phonics', 'item_code' => 'PHON',
            'participant_type' => 'individual', 'stage_type' => 'off_stage', 'is_enabled' => true,
        ]);
        $pairItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'News Writing', 'item_code' => 'NWPR',
            'participant_type' => 'pair', 'stage_type' => 'off_stage', 'min_group_size' => 2, 'max_group_size' => 2, 'is_enabled' => true,
        ]);
        $grpItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Choral Reading', 'item_code' => 'CHOR',
            'participant_type' => 'group', 'stage_type' => 'on_stage', 'min_group_size' => 4, 'max_group_size' => 10, 'is_enabled' => true,
        ]);

        $this->registerStudent($event, $schoolId, $studentId, $indItem);
        $this->registerStudent($event, $schoolId, $studentId, $pairItem);

        $service = new FestParticipationLimitService($event);
        $errors = $service->validateRegistration($grpItem, $schoolId, [$studentId]);

        $this->assertNotEmpty($errors, 'A student cannot register for a 3rd item exceeding max overall 2 items');
        $this->assertStringContainsString('total items for this event', implode(' ', $errors));
    }

    public function test_pair_items_point_calculation_uses_individual_scale(): void
    {
        [$event, $schoolId] = $this->fixture(['pair_points_mode' => 'individual']);

        $pairItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Be The Character', 'item_code' => 'PAIR1',
            'participant_type' => 'pair', 'stage_type' => 'on_stage', 'is_enabled' => true,
        ]);
        $indItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Spelling Marathon', 'item_code' => 'SPEL',
            'participant_type' => 'individual', 'stage_type' => 'off_stage', 'is_enabled' => true,
        ]);
        $grpItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Choral Reading', 'item_code' => 'CHOR',
            'participant_type' => 'group', 'stage_type' => 'on_stage', 'is_enabled' => true,
        ]);

        $gradeService = app(FestGradePointService::class);

        $indMark = new FestMark(['grade' => 'A', 'position' => 1, 'score' => 85]);
        $indMark->setRelation('item', $indItem);

        $pairMark = new FestMark(['grade' => 'A', 'position' => 1, 'score' => 85]);
        $pairMark->setRelation('item', $pairItem);

        $grpMark = new FestMark(['grade' => 'A', 'position' => 1, 'score' => 85]);
        $grpMark->setRelation('item', $grpItem);

        $indPoints = $gradeService->pointsForMark($event, $indMark);
        $pairPoints = $gradeService->pointsForMark($event, $pairMark);
        $grpPoints = $gradeService->pointsForMark($event, $grpMark);

        // Individual 1st place with Grade A = 8 points
        // Group 1st place with Grade A = 16 points (double)
        $this->assertSame(8, $indPoints);
        $this->assertSame(8, $pairPoints, 'Pair item must receive INDIVIDUAL points when pair_points_mode is individual');
        $this->assertSame(16, $grpPoints, 'Group item must receive GROUP points');
    }
}
