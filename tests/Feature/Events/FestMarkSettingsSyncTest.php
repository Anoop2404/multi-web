<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestMarkCriterion;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestMarkCriteriaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestMarkSettingsSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_criteria_with_sync_option_copies_criteria_judge_count_and_total_marks_to_child_events(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Sync Criteria Sahodaya',
            'domain' => Str::uuid() . '.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'SC', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Wayanad Sahodaya Kalotsav',
            'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned',
            'workflow_mode' => 'phased_regional_billing',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $phase1 = FestEventPhase::create(['event_id' => $hub->id, 'name' => 'PHASE 1', 'code' => 'P1', 'sort_order' => 1, 'is_regional' => false]);
        $phase2 = FestEventPhase::create(['event_id' => $hub->id, 'name' => 'PHASE 2', 'code' => 'P2', 'sort_order' => 2, 'is_regional' => false]);

        $phase1Leaf = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Wayanad Sahodaya Kalotsav — PHASE 1', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
            'parent_event_id' => $hub->id, 'source_phase_id' => $phase1->id, 'partition_role' => 'phase',
        ]);
        $phase2Leaf = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Wayanad Sahodaya Kalotsav — PHASE 2', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
            'parent_event_id' => $hub->id, 'source_phase_id' => $phase2->id, 'partition_role' => 'phase',
        ]);

        $item1 = FestEventItem::create([
            'event_id' => $phase1Leaf->id, 'title' => 'Recitation-Malayalam', 'item_code' => '101',
            'stage_type' => 'on_stage', 'participant_type' => 'individual', 'category' => 'music',
            'class_group' => 'category_1', 'is_enabled' => true,
        ]);
        $item2 = FestEventItem::create([
            'event_id' => $phase2Leaf->id, 'title' => 'Recitation-Malayalam', 'item_code' => '101',
            'stage_type' => 'on_stage', 'participant_type' => 'individual', 'category' => 'music',
            'class_group' => 'category_1', 'is_enabled' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.items.mark-criteria.save', [
            'tenantId' => $sahodaya->id,
            'event' => $phase1Leaf->id,
            'item' => $item1->id,
        ]), [
            'judge_count' => 3,
            'total_marks' => 300,
            'sync_to_child_events' => true,
            'criteria' => [
                ['label' => 'Suitable facial expressions', 'max_score' => 25],
                ['label' => 'Imbibing the meaning and message', 'max_score' => 25],
                ['label' => 'Clarity and correct pronunciation', 'max_score' => 25],
                ['label' => 'Appropriate introduction', 'max_score' => 25],
            ],
        ]);

        $response->assertRedirect();

        $item1->refresh();
        $item2->refresh();

        $this->assertEquals(300, $item1->total_marks);
        $this->assertEquals(300, $item2->total_marks);

        $criteriaService = app(FestMarkCriteriaService::class);
        $this->assertEquals(3, $criteriaService->judgeCountForItem($item1));
        $this->assertEquals(3, $criteriaService->judgeCountForItem($item2));

        $item2Criteria = FestMarkCriterion::where('item_id', $item2->id)->orderBy('sort_order')->pluck('label')->all();
        $this->assertEquals([
            'Suitable facial expressions',
            'Imbibing the meaning and message',
            'Clarity and correct pronunciation',
            'Appropriate introduction',
        ], $item2Criteria);
    }
}
