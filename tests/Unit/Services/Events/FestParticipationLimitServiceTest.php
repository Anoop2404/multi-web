<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestParticipationPolicy;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Services\Events\FestParticipationLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A group/team item counts only against the group quota, never on-stage/off-stage,
 * regardless of its stage_type — decided 2026-08-19 after the previous behavior (an
 * on-stage group item drew from both buckets at once) surprised the user. See
 * FestParticipationLimitService::itemDimensions() for the single source of truth this
 * now goes through.
 */
class FestParticipationLimitServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(array $policyOverrides = []): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Quota Test Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'QTS', 'student_data_mode' => 'counts_only']);
        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Quota Test School', 'domain' => Str::uuid().'.test', 'membership_status' => 'approved', 'is_active' => true,
        ]);
        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);
        FestParticipationPolicy::create(array_merge([
            'tenant_id' => $sahodaya->id, 'scope' => 'event', 'event_id' => $event->id, 'is_active' => true,
            'max_onstage_per_student' => 3, 'max_offstage_per_student' => 3, 'max_group_per_student' => 2,
            'one_entry_per_item_per_school' => false, 'count_submitted_registrations' => true,
        ], $policyOverrides));

        return [$event, $school->id];
    }

    private function registerStudentFor(FestEvent $event, string $schoolId, int $studentId, FestEventItem $item): void
    {
        // fest_participants.participant_type is student|teacher (who they are), unrelated
        // to fest_event_items.participant_type (individual|group|team, what the item is).
        $reg = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $schoolId, 'status' => 'submitted', 'submitted_at' => now()]);
        FestParticipant::create(['registration_id' => $reg->id, 'student_id' => $studentId, 'participant_type' => 'student']);
    }

    public function test_onstage_group_item_does_not_draw_from_the_onstage_cap(): void
    {
        [$event, $schoolId] = $this->fixture();
        $studentId = 1;

        // Student already has 3 on-stage INDIVIDUAL items -- at the on-stage cap.
        for ($i = 1; $i <= 3; $i++) {
            $item = FestEventItem::create(['event_id' => $event->id, 'title' => "Onstage {$i}", 'item_code' => "OS{$i}", 'stage_type' => 'on_stage', 'participant_type' => 'individual', 'is_enabled' => true]);
            $this->registerStudentFor($event, $schoolId, $studentId, $item);
        }

        $groupItem = FestEventItem::create(['event_id' => $event->id, 'title' => 'Group Dance', 'item_code' => 'GRP1', 'stage_type' => 'on_stage', 'participant_type' => 'group', 'min_group_size' => 1, 'max_group_size' => 10, 'is_enabled' => true]);

        $service = new FestParticipationLimitService($event);
        $errors = $service->validateRegistration($groupItem, $schoolId, [$studentId]);

        $this->assertSame([], $errors, 'an on-stage group item must not be blocked by an already-full on-stage cap: '.implode(' | ', $errors));
    }

    public function test_onstage_group_item_still_respects_the_group_cap(): void
    {
        [$event, $schoolId] = $this->fixture();
        $studentId = 1;

        // Student already has 2 group items -- at the group cap (max_group_per_student=2).
        for ($i = 1; $i <= 2; $i++) {
            $item = FestEventItem::create(['event_id' => $event->id, 'title' => "Group {$i}", 'item_code' => "GX{$i}", 'stage_type' => 'on_stage', 'participant_type' => 'group', 'min_group_size' => 1, 'max_group_size' => 10, 'is_enabled' => true]);
            $this->registerStudentFor($event, $schoolId, $studentId, $item);
        }

        $thirdGroupItem = FestEventItem::create(['event_id' => $event->id, 'title' => 'Group Song', 'item_code' => 'GX3', 'stage_type' => 'on_stage', 'participant_type' => 'group', 'min_group_size' => 1, 'max_group_size' => 10, 'is_enabled' => true]);

        $service = new FestParticipationLimitService($event);
        $errors = $service->validateRegistration($thirdGroupItem, $schoolId, [$studentId]);

        $this->assertNotEmpty($errors, 'the group cap must still block a 3rd group item even though on-stage has room');
        $this->assertStringContainsString('group items', implode(' ', $errors));
    }

    public function test_usage_badges_count_group_items_only_under_group(): void
    {
        [$event, $schoolId] = $this->fixture();

        $onStageIndividual = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo', 'item_code' => 'SOLO1', 'stage_type' => 'on_stage', 'participant_type' => 'individual', 'is_enabled' => true]);
        $onStageGroup = FestEventItem::create(['event_id' => $event->id, 'title' => 'Group Dance', 'item_code' => 'GRP2', 'stage_type' => 'on_stage', 'participant_type' => 'group', 'min_group_size' => 1, 'max_group_size' => 10, 'is_enabled' => true]);

        $this->registerStudentFor($event, $schoolId, 1, $onStageIndividual);
        $this->registerStudentFor($event, $schoolId, 2, $onStageGroup);

        $service = new FestParticipationLimitService($event);
        $usage = $service->usageForSchool($schoolId);

        $this->assertSame(1, $usage['used']['on_stage'], 'only the individual item should count as on-stage');
        $this->assertSame(1, $usage['used']['group'], 'the group item should count as group');
    }

    public function test_offstage_writing_submax_blocks_a_second_writing_item_even_under_the_general_offstage_cap(): void
    {
        [$event, $schoolId] = $this->fixture(['max_offstage_writing_per_student' => 1]);
        $studentId = 1;

        $essay = FestEventItem::create(['event_id' => $event->id, 'title' => 'Essay Writing', 'item_code' => 'WR1', 'category' => 'literary', 'stage_type' => 'off_stage', 'participant_type' => 'individual', 'is_enabled' => true]);
        $this->registerStudentFor($event, $schoolId, $studentId, $essay);

        $story = FestEventItem::create(['event_id' => $event->id, 'title' => 'Story Writing', 'item_code' => 'WR2', 'category' => 'literary', 'stage_type' => 'off_stage', 'participant_type' => 'individual', 'is_enabled' => true]);

        $service = new FestParticipationLimitService($event);
        $errors = $service->validateRegistration($story, $schoolId, [$studentId]);

        $this->assertNotEmpty($errors, 'a second writing item must be blocked once the writing sub-cap is reached');
        $this->assertStringContainsString('writing items', implode(' ', $errors));
    }

    public function test_offstage_drawing_submax_does_not_block_a_writing_item(): void
    {
        [$event, $schoolId] = $this->fixture(['max_offstage_drawing_per_student' => 1]);
        $studentId = 1;

        $drawing = FestEventItem::create(['event_id' => $event->id, 'title' => 'Pencil Drawing', 'item_code' => 'DR1', 'category' => 'fine_arts', 'stage_type' => 'off_stage', 'participant_type' => 'individual', 'is_enabled' => true]);
        $this->registerStudentFor($event, $schoolId, $studentId, $drawing);

        $essay = FestEventItem::create(['event_id' => $event->id, 'title' => 'Essay Writing', 'item_code' => 'WR3', 'category' => 'literary', 'stage_type' => 'off_stage', 'participant_type' => 'individual', 'is_enabled' => true]);

        $service = new FestParticipationLimitService($event);
        $errors = $service->validateRegistration($essay, $schoolId, [$studentId]);

        $this->assertSame([], $errors, 'a filled drawing sub-cap must not block a writing item: '.implode(' | ', $errors));
    }
}
