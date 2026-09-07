<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestMarkCriterion;
use App\Models\FestScoringRubricTemplate;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestBulkApplyRubricTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_apply_assigns_a_template_to_every_selected_item_at_once(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Bulk Rubric Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'BR', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $itemA = FestEventItem::create(['event_id' => $event->id, 'title' => 'Pencil Drawing Cat I', 'participant_type' => 'individual', 'is_enabled' => true]);
        $itemB = FestEventItem::create(['event_id' => $event->id, 'title' => 'Pencil Drawing Cat II', 'participant_type' => 'individual', 'is_enabled' => true]);
        $untouched = FestEventItem::create(['event_id' => $event->id, 'title' => 'Essay Writing Cat I', 'participant_type' => 'individual', 'is_enabled' => true]);

        $template = FestScoringRubricTemplate::create(['tenant_id' => $sahodaya->id, 'name' => 'Pencil Drawing', 'sort_order' => 0]);
        foreach ([['Proportion', 25], ['Neatness', 25], ['Imagination and beauty of the strokes', 25], ['Clarity of the theme', 25]] as [$label, $max]) {
            $template->criteria()->create(['tenant_id' => $sahodaya->id, 'label' => $label, 'max_score' => $max, 'sort_order' => 0]);
        }

        $response = $this->actingAs($admin)->post(route('sahodaya.events.mark-settings.bulk-apply-template', [
            'tenantId' => $sahodaya->id,
            'event' => $event->id,
        ]), [
            'template_id' => $template->id,
            'item_ids' => [$itemA->id, $itemB->id],
        ]);

        $response->assertSessionHas('success');

        $this->assertSame(
            ['Proportion', 'Neatness', 'Imagination and beauty of the strokes', 'Clarity of the theme'],
            FestMarkCriterion::where('item_id', $itemA->id)->orderBy('sort_order')->pluck('label')->all(),
        );
        $this->assertSame(
            ['Proportion', 'Neatness', 'Imagination and beauty of the strokes', 'Clarity of the theme'],
            FestMarkCriterion::where('item_id', $itemB->id)->orderBy('sort_order')->pluck('label')->all(),
        );
        $this->assertSame([], FestMarkCriterion::where('item_id', $untouched->id)->pluck('label')->all());
    }

    public function test_bulk_apply_with_sync_propagates_to_matching_items_on_a_region_partition_child(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Bulk Rubric Sync Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'BRS', 'student_data_mode' => 'counts_only']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Kalotsav Hub', 'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned', 'workflow_mode' => 'phased_regional_billing',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $phase = FestEventPhase::create(['event_id' => $hub->id, 'name' => 'PHASE 1', 'code' => 'P1', 'sort_order' => 1, 'is_regional' => false]);

        $leaf = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Kalotsav Hub — PHASE 1', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
            'parent_event_id' => $hub->id, 'source_phase_id' => $phase->id, 'partition_role' => 'phase',
        ]);
        $region = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Kalotsav Hub — PHASE 1 — Region A', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
            'parent_event_id' => $hub->id, 'source_phase_id' => $phase->id, 'partition_role' => 'region',
        ]);

        $sourceItem = FestEventItem::create([
            'event_id' => $leaf->id, 'title' => 'Pencil Drawing', 'item_code' => 'ART-01',
            'participant_type' => 'individual', 'is_enabled' => true,
        ]);
        $regionItem = FestEventItem::create([
            'event_id' => $region->id, 'title' => 'Pencil Drawing', 'item_code' => 'ART-01',
            'participant_type' => 'individual', 'is_enabled' => true,
        ]);

        $template = FestScoringRubricTemplate::create(['tenant_id' => $sahodaya->id, 'name' => 'Pencil Drawing', 'sort_order' => 0]);
        $template->criteria()->create(['tenant_id' => $sahodaya->id, 'label' => 'Proportion', 'max_score' => 25, 'sort_order' => 0]);
        $template->criteria()->create(['tenant_id' => $sahodaya->id, 'label' => 'Neatness', 'max_score' => 25, 'sort_order' => 1]);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.mark-settings.bulk-apply-template', [
            'tenantId' => $sahodaya->id,
            'event' => $leaf->id,
        ]), [
            'template_id' => $template->id,
            'item_ids' => [$sourceItem->id],
            'sync_to_child_events' => true,
        ]);

        $response->assertSessionHas('success');

        $this->assertSame(
            ['Proportion', 'Neatness'],
            FestMarkCriterion::where('item_id', $regionItem->id)->orderBy('sort_order')->pluck('label')->all(),
            'the bulk-applied template must propagate to the matching item on the region partition copy',
        );
    }
}
