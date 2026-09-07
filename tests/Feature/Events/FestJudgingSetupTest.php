<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestScoringRubricTemplate;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestMarkCriteriaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestJudgingSetupTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, admin: User, hub: FestEvent, leaf: FestEvent, regionA: FestEvent, regionB: FestEvent} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Judging Setup Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'JS', 'student_data_mode' => 'counts_only']);

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
        $regionA = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Kalotsav Hub — PHASE 1 — Region A', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
            'parent_event_id' => $hub->id, 'source_phase_id' => $phase->id, 'partition_role' => 'region',
        ]);
        $regionB = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Kalotsav Hub — PHASE 1 — Region B', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
            'parent_event_id' => $hub->id, 'source_phase_id' => $phase->id, 'partition_role' => 'region',
        ]);

        return compact('sahodaya', 'admin', 'hub', 'leaf', 'regionA', 'regionB');
    }

    public function test_judging_setup_groups_the_hub_and_every_region_partitions_copy_into_one_row(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'leaf' => $leaf, 'regionA' => $regionA, 'regionB' => $regionB] = $this->fixture();

        $leafItem = FestEventItem::create(['event_id' => $leaf->id, 'title' => 'Pencil Drawing', 'item_code' => 'ART-01', 'participant_type' => 'individual', 'is_enabled' => true, 'total_marks' => 100]);
        $itemA = FestEventItem::create(['event_id' => $regionA->id, 'title' => 'Pencil Drawing', 'item_code' => 'ART-01', 'participant_type' => 'individual', 'is_enabled' => true, 'total_marks' => 100]);
        $itemB = FestEventItem::create(['event_id' => $regionB->id, 'title' => 'Pencil Drawing', 'item_code' => 'ART-01', 'participant_type' => 'individual', 'is_enabled' => true, 'total_marks' => 100]);

        $template = FestScoringRubricTemplate::create(['tenant_id' => $sahodaya->id, 'name' => 'Pencil Drawing', 'sort_order' => 0]);
        $template->criteria()->create(['tenant_id' => $sahodaya->id, 'label' => 'Proportion', 'max_score' => 50, 'sort_order' => 0]);
        $template->criteria()->create(['tenant_id' => $sahodaya->id, 'label' => 'Neatness', 'max_score' => 50, 'sort_order' => 1]);

        // Both criteria and total marks are common across the whole item family — applying
        // the template to region A alone must still propagate to region B and the phase leaf.
        $this->actingAs($admin)->post(route('sahodaya.events.items.mark-criteria.apply-template', [
            'tenantId' => $sahodaya->id, 'event' => $regionA->id, 'item' => $itemA->id,
        ]), ['template_id' => $template->id]);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.judging-setup.index', [
            'tenantId' => $sahodaya->id, 'event' => $leaf->id,
        ]));

        $response->assertOk();
        $rows = collect($response->viewData('page')['props']['items']);

        $this->assertCount(1, $rows, 'the phase leaf item and both region copies must collapse into a single row');

        $row = $rows->first();
        $this->assertSame('Pencil Drawing', $row['title']);
        $this->assertSame('Pencil Drawing', $row['rubric_status'], 'the judging sheet propagated to every region, so the group-level rubric status must reflect it');

        $regionItemIds = collect($row['regions'])->pluck('item_id')->sort()->values()->all();
        $this->assertSame(
            collect([$leafItem->id, $itemA->id, $itemB->id])->sort()->values()->all(),
            $regionItemIds,
            'the row must list a judge-count slot for every underlying item across the family',
        );
    }

    public function test_applying_a_judging_sheet_syncs_criteria_and_total_marks_but_never_judge_count(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'regionA' => $regionA, 'regionB' => $regionB] = $this->fixture();

        $itemA = FestEventItem::create(['event_id' => $regionA->id, 'title' => 'Essay Writing', 'item_code' => 'LIT-01', 'participant_type' => 'individual', 'is_enabled' => true, 'total_marks' => 50]);
        $itemB = FestEventItem::create(['event_id' => $regionB->id, 'title' => 'Essay Writing', 'item_code' => 'LIT-01', 'participant_type' => 'individual', 'is_enabled' => true, 'total_marks' => 80]);

        $criteriaService = app(FestMarkCriteriaService::class);
        $criteriaService->setJudgeCount($itemA, 2);
        $criteriaService->setJudgeCount($itemB, 5);

        $template = FestScoringRubricTemplate::create(['tenant_id' => $sahodaya->id, 'name' => 'Essay Writing', 'sort_order' => 0]);
        $template->criteria()->create(['tenant_id' => $sahodaya->id, 'label' => 'Content', 'max_score' => 60, 'sort_order' => 0]);
        $template->criteria()->create(['tenant_id' => $sahodaya->id, 'label' => 'Language', 'max_score' => 40, 'sort_order' => 1]);

        $this->actingAs($admin)->post(route('sahodaya.events.items.mark-criteria.apply-template', [
            'tenantId' => $sahodaya->id, 'event' => $regionA->id, 'item' => $itemA->id,
        ]), ['template_id' => $template->id])->assertSessionHas('success');

        $itemA->refresh();
        $itemB->refresh();

        // Applying a template only replaces criteria on the source item — it doesn't compute
        // a new total marks value, it just propagates whatever total marks the source already
        // has (50) out to matching items, so both end up in sync at that same value.
        $this->assertSame('50.00', $itemA->total_marks);
        $this->assertSame('50.00', $itemB->total_marks, 'total marks is common and must sync to region B');

        $this->assertSame(
            ['Content', 'Language'],
            \App\Models\FestMarkCriterion::where('item_id', $itemB->id)->orderBy('sort_order')->pluck('label')->all(),
            'criteria is common and must sync to region B',
        );

        $this->assertSame(2, $criteriaService->judgeCountForItem($itemA->fresh()));
        $this->assertSame(5, $criteriaService->judgeCountForItem($itemB->fresh()), 'judge count is the one field that must never sync — region B keeps its own value');
    }

    public function test_saving_total_marks_syncs_the_family_but_saving_judge_count_stays_per_region(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin, 'regionA' => $regionA, 'regionB' => $regionB] = $this->fixture();

        $itemA = FestEventItem::create(['event_id' => $regionA->id, 'title' => 'Essay Writing', 'item_code' => 'LIT-01', 'participant_type' => 'individual', 'is_enabled' => true, 'total_marks' => 100]);
        $itemB = FestEventItem::create(['event_id' => $regionB->id, 'title' => 'Essay Writing', 'item_code' => 'LIT-01', 'participant_type' => 'individual', 'is_enabled' => true, 'total_marks' => 100]);

        // Total marks: saving region A's value must propagate to region B.
        $this->actingAs($admin)->post(route('sahodaya.events.mark-settings.sync-total-marks', [
            'tenantId' => $sahodaya->id, 'event' => $regionA->id,
        ]), ['items' => [['id' => $itemA->id, 'total_marks' => 150]]])->assertSessionHas('success');

        // Judge count: saving region A's value must NOT propagate to region B.
        $this->actingAs($admin)->post(route('sahodaya.events.mark-settings.judge-count', [
            'tenantId' => $sahodaya->id, 'event' => $regionA->id,
        ]), ['items' => [['id' => $itemA->id, 'judge_count' => 4]]])->assertSessionHas('success');

        $criteriaService = app(FestMarkCriteriaService::class);
        $itemA->refresh();
        $itemB->refresh();

        $this->assertSame('150.00', $itemA->total_marks);
        $this->assertSame('150.00', $itemB->total_marks, 'total marks must sync to region B');

        $this->assertSame(4, $criteriaService->judgeCountForItem($itemA));
        $this->assertSame(1, $criteriaService->judgeCountForItem($itemB), 'judge count must stay untouched on region B');
    }
}
