<?php

namespace Tests\Feature\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression coverage for a live incident on Wayanad Sahodaya: a phased_regional_billing
 * leaf's item table can hold rows whose phase_id resolves (via FestEventPhase::sourcePhase,
 * the same one-hop walk FestRegistrationRouterService::resolvePhasedTarget() uses) to a
 * DIFFERENT phase than the leaf's own source_phase_id — e.g. a Phase 2 item sitting in
 * Phase 1's item table. hydrateEventForSchoolRegistration() previously served
 * $event->items with no phase filter at all, so that foreign-phase item was listed and
 * selectable on Phase 1's registration page. Registering it didn't error: the router
 * correctly read the item's own phase tag and silently routed the write to Phase 2's
 * operational event instead, so the school saw a success toast but the item never showed
 * as registered on the Phase 1 page they submitted it from (confirmed live: item
 * "Light Music-Malayalam", code 104, phase-tagged for PHASE 2 but living in PHASE 1's
 * item table). Fixed by scoping the item list to the viewed event's own phase.
 */
class FestRegistrationPhaseScopedItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_excludes_items_mistagged_for_a_different_phase(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Phase Scoped Items Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'PS',
            'student_data_mode' => 'counts_only',
        ]);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Phase Scoped Items School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $schoolAdmin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $schoolAdmin->assignRole('school_admin');

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Phase Scoped Items Kalotsav', 'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned', 'workflow_mode' => 'phased_regional_billing',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $phase1 = FestEventPhase::create(['event_id' => $hub->id, 'name' => 'PHASE 1', 'code' => 'P1', 'sort_order' => 1, 'is_regional' => false]);
        $phase2 = FestEventPhase::create(['event_id' => $hub->id, 'name' => 'PHASE 2', 'code' => 'P2', 'sort_order' => 2, 'is_regional' => false]);

        $phase1Leaf = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Phase Scoped Items Kalotsav — PHASE 1', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
            'parent_event_id' => $hub->id, 'source_phase_id' => $phase1->id, 'partition_role' => 'phase',
        ]);

        // Correctly tagged: lives in the Phase 1 leaf's item table AND its phase_id
        // resolves to Phase 1. Must stay visible/registrable.
        $goodItem = FestEventItem::create([
            'event_id' => $phase1Leaf->id, 'title' => 'Recitation-Malayalam', 'item_code' => '101',
            'stage_type' => 'on_stage', 'participant_type' => 'individual', 'category' => 'music',
            'phase_id' => $phase1->id, 'is_enabled' => true,
        ]);

        // The bug: this row lives in the Phase 1 leaf's item table too, but its phase_id
        // resolves to Phase 2 — must be excluded from the Phase 1 page.
        $mistaggedItem = FestEventItem::create([
            'event_id' => $phase1Leaf->id, 'title' => 'Light Music-Malayalam', 'item_code' => '104',
            'stage_type' => 'on_stage', 'participant_type' => 'individual', 'category' => 'music',
            'phase_id' => $phase2->id, 'is_enabled' => true,
        ]);

        // Never phase-tagged at all (legacy/non-phased item) — filter must fail open and
        // keep it, not treat "no phase_id" as a mismatch.
        $untaggedItem = FestEventItem::create([
            'event_id' => $phase1Leaf->id, 'title' => 'Legacy Item', 'item_code' => '999',
            'stage_type' => 'on_stage', 'participant_type' => 'individual', 'category' => 'general',
            'phase_id' => null, 'is_enabled' => true,
        ]);

        $response = $this->actingAs($schoolAdmin)->get(route('school.kalotsav.event.registration', [
            'tenantId' => $school->id,
            'event' => $phase1Leaf->id,
        ]));

        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $hydratedEvent = collect($props['events'])->first();
        $itemIds = collect($hydratedEvent['items'])->pluck('id')->all();

        $this->assertContains($goodItem->id, $itemIds, 'correctly phase-tagged item must be listed on its own phase\'s page');
        $this->assertContains($untaggedItem->id, $itemIds, 'never-phase-tagged item must still be listed (fail-open)');
        $this->assertNotContains($mistaggedItem->id, $itemIds, 'item tagged for a DIFFERENT phase must not be listed/registrable here — this is the live-incident regression');
    }
}
